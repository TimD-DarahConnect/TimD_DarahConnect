<?php

namespace App\Http\Controllers\DonorCommitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\DonorCommitment\StoreCoordinationMessageRequest;
use App\Http\Requests\DonorCommitment\UpdateMeetingPointRequest;
use App\Models\CoordinationMessage;
use App\Models\DonorCommitment;
use App\Services\DonorCommitment\CoordinationPrivacyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MeetingPointCoordinationController extends Controller
{
    public function __construct(
        private readonly CoordinationPrivacyService $privacyService
    ) {}

    /**
     * Display the privacy-safe structured hospital meeting-point coordination room
     * strictly authorized between the owning Blood Seeker and the committed Volunteer Donor.
     */
    public function show(Request $request, DonorCommitment $commitment): View
    {
        $user = $request->user();
        $commitment->load(['donor', 'bloodRequest.requester', 'bloodRequest.facility.region']);

        $isOwnerDonor = $user->isDonor() && (int) $commitment->donor_id === (int) $user->id;
        $isOwnerSeeker = $user->isSeeker() && (int) $commitment->bloodRequest?->requester_id === (int) $user->id;

        if (! $isOwnerDonor && ! $isOwnerSeeker) {
            abort(403, 'Ruang koordinasi ini bersifat privat hanya antara pemohon terkait dan relawan pendonor berkomitmen.');
        }

        // Mark incoming unread messages from the counterpart as read
        CoordinationMessage::query()
            ->where('donor_commitment_id', $commitment->id)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = $commitment->coordinationMessages()
            ->with('sender')
            ->get();

        $roleKey = $isOwnerDonor ? 'pendonor' : 'pencari_darah';

        return view('donor-commitments.coordination', [
            'commitment' => $commitment,
            'bloodRequest' => $commitment->bloodRequest,
            'messages' => $messages,
            'isOwnerDonor' => $isOwnerDonor,
            'isOwnerSeeker' => $isOwnerSeeker,
            'canCoordinateActively' => $commitment->canCoordinateActively(),
            'maskedDonorAlias' => $commitment->masked_donor_alias,
            'maskedSeekerAlias' => $this->privacyService->maskSeekerAlias($commitment->bloodRequest?->requester),
            'quickPresets' => $this->privacyService->presetsForRole($roleKey),
        ]);
    }

    /**
     * Update the structured hospital landmark / BDRS counter details (Seeker only),
     * automatically masking any accidental phone/email PII.
     */
    public function updateMeetingPoint(UpdateMeetingPointRequest $request, DonorCommitment $commitment): RedirectResponse
    {
        $commitment->loadMissing('bloodRequest');

        if (! $commitment->canCoordinateActively()) {
            return redirect()
                ->route('coordinations.show', $commitment)
                ->with('warning', 'Titik temu tidak dapat diperbarui karena status komitmen atau permohonan sudah tidak aktif.');
        }

        $sanitizedPoint = $this->privacyService->sanitizeText((string) $request->validated('meeting_point'));
        $sanitizedFloor = $this->privacyService->sanitizeText($request->validated('building_floor'));
        $sanitizedCompanion = $this->privacyService->sanitizeText($request->validated('companion_alias'));
        $sanitizedInstructions = $this->privacyService->sanitizeText($request->validated('meeting_instructions'));

        $wasRedacted = $sanitizedPoint['redacted']
            || $sanitizedFloor['redacted']
            || $sanitizedCompanion['redacted']
            || $sanitizedInstructions['redacted'];

        $user = $request->user();

        DB::transaction(function () use (
            $commitment,
            $user,
            $sanitizedPoint,
            $sanitizedFloor,
            $sanitizedCompanion,
            $sanitizedInstructions,
            $wasRedacted
        ): void {
            /** @var DonorCommitment $locked */
            $locked = DonorCommitment::query()
                ->whereKey($commitment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->update([
                'meeting_point' => $sanitizedPoint['clean_text'],
                'building_floor' => $sanitizedFloor['clean_text'] ?: null,
                'companion_alias' => $sanitizedCompanion['clean_text'] ?: null,
                'meeting_instructions' => $sanitizedInstructions['clean_text'] ?: null,
                'meeting_point_updated_at' => now(),
                'meeting_point_confirmed_at' => null,
            ]);

            $summaryParts = ['Titik temu faskes diperbarui ke: '.$sanitizedPoint['clean_text']];
            if ($sanitizedFloor['clean_text'] !== '') {
                $summaryParts[] = 'Lokasi: '.$sanitizedFloor['clean_text'];
            }

            CoordinationMessage::create([
                'donor_commitment_id' => $locked->id,
                'sender_id' => $user->id,
                'sender_role' => 'pencari_darah',
                'message_type' => 'meeting_point_update',
                'preset_key' => null,
                'body' => implode(' · ', $summaryParts),
                'contained_masked_contact' => $wasRedacted,
            ]);
        });

        if ($wasRedacted) {
            return redirect()
                ->route('coordinations.show', $commitment)
                ->with('warning', 'Titik temu faskes berhasil diperbarui. Nomor telepon atau kontak pribadi yang terdeteksi telah disamarkan otomatis demi perlindungan privasi.');
        }

        return redirect()
            ->route('coordinations.show', $commitment)
            ->with('success', 'Rincian titik temu di rumah sakit berhasil diperbarui untuk panduan relawan pendonor.');
    }

    /**
     * Allow the committed Volunteer Donor to acknowledge & confirm understanding of the hospital meeting point.
     */
    public function confirmMeetingPoint(Request $request, DonorCommitment $commitment): RedirectResponse
    {
        $user = $request->user();
        $commitment->loadMissing('bloodRequest');

        if (! $user->isDonor() || (int) $commitment->donor_id !== (int) $user->id) {
            abort(403, 'Hanya relawan pendonor berkomitmen yang dapat mengonfirmasi titik temu ini.');
        }

        if (! $commitment->canCoordinateActively()) {
            return redirect()
                ->route('coordinations.show', $commitment)
                ->with('warning', 'Konfirmasi titik temu tidak tersedia karena komitmen sudah tidak aktif.');
        }

        if ($commitment->meeting_point_confirmed_at !== null) {
            return redirect()
                ->route('coordinations.show', $commitment)
                ->with('status', 'Titik temu rumah sakit sudah Anda konfirmasi sebelumnya.');
        }

        DB::transaction(function () use ($commitment, $user): void {
            /** @var DonorCommitment $locked */
            $locked = DonorCommitment::query()
                ->whereKey($commitment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->meeting_point_confirmed_at !== null) {
                return;
            }

            $locked->update([
                'meeting_point_confirmed_at' => now(),
            ]);

            $pointLabel = $locked->meeting_point ?: 'Instalasi Bank Darah Rumah Sakit (BDRS)';

            CoordinationMessage::create([
                'donor_commitment_id' => $locked->id,
                'sender_id' => $user->id,
                'sender_role' => 'pendonor',
                'message_type' => 'arrival_check',
                'preset_key' => 'confirm_meeting_point',
                'body' => "Saya telah membaca dan mengonfirmasi titik temu di {$pointLabel}.",
                'contained_masked_contact' => false,
            ]);
        });

        return redirect()
            ->route('coordinations.show', $commitment)
            ->with('success', 'Titik temu rumah sakit berhasil dikonfirmasi! Perwakilan keluarga kini mengetahui Anda telah memahami lokasi loket.');
    }

    /**
     * Store a structured preset or short contextual coordination message with strict PII redaction.
     */
    public function storeMessage(StoreCoordinationMessageRequest $request, DonorCommitment $commitment): RedirectResponse
    {
        $commitment->loadMissing('bloodRequest');

        if (! $commitment->canCoordinateActively()) {
            return redirect()
                ->route('coordinations.show', $commitment)
                ->with('warning', 'Pengiriman pesan baru ditutup karena status komitmen atau permohonan sudah selesai/dibatalkan.');
        }

        $user = $request->user();
        $senderRole = $user->isDonor() ? 'pendonor' : 'pencari_darah';
        $presets = $this->privacyService->presetsForRole($senderRole);

        $presetKey = $request->validated('preset_key');
        $messageType = 'contextual_note';
        $rawBody = trim((string) $request->validated('body'));

        if ($presetKey !== null && isset($presets[$presetKey])) {
            $presetMeta = $presets[$presetKey];
            $messageType = $presetMeta['type'];
            if ($rawBody === '') {
                $rawBody = $presetMeta['body'];
            }
        }

        $sanitized = $this->privacyService->sanitizeText($rawBody);

        CoordinationMessage::create([
            'donor_commitment_id' => $commitment->id,
            'sender_id' => $user->id,
            'sender_role' => $senderRole,
            'message_type' => $messageType,
            'preset_key' => $presetKey,
            'body' => $sanitized['clean_text'],
            'contained_masked_contact' => $sanitized['redacted'],
        ]);

        if ($sanitized['redacted']) {
            return redirect()
                ->route('coordinations.show', $commitment)
                ->with('warning', 'Pesan koordinasi terkirim. Demi keamanan privasi bersama, nomor ponsel atau kontak pribadi telah disamarkan otomatis oleh sistem.');
        }

        return redirect()
            ->route('coordinations.show', $commitment)
            ->with('success', 'Pesan koordinasi titik temu berhasil dikirim.');
    }
}
