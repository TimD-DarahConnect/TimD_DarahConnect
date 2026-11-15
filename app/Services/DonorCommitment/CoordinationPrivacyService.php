<?php

namespace App\Services\DonorCommitment;

use App\Models\User;

class CoordinationPrivacyService
{
    public const REDACTION_PLACEHOLDER = '[Kontak Pribadi Disamarkan]';

    /**
     * Sanitize structured coordination text by stripping/masking personal phone numbers,
     * messaging links (WhatsApp/Telegram), and email addresses so personal contact details
     * are never exposed in the coordination room.
     *
     * @return array{clean_text: string, redacted: bool}
     */
    public function sanitizeText(?string $input): array
    {
        $raw = trim((string) $input);
        if ($raw === '') {
            return [
                'clean_text' => '',
                'redacted' => false,
            ];
        }

        $redacted = false;
        $cleaned = $raw;

        // 1. Mask email addresses
        $emailPattern = '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i';
        if (preg_match($emailPattern, $cleaned) === 1) {
            $redacted = true;
            $cleaned = (string) preg_replace($emailPattern, self::REDACTION_PLACEHOLDER, $cleaned);
        }

        // 2. Mask external messaging deep links (wa.me, t.me, api.whatsapp.com)
        $linkPattern = '/(?:https?:\/\/)?(?:wa\.me|api\.whatsapp\.com|t\.me)\/\S+/i';
        if (preg_match($linkPattern, $cleaned) === 1) {
            $redacted = true;
            $cleaned = (string) preg_replace($linkPattern, self::REDACTION_PLACEHOLDER, $cleaned);
        }

        // 3. Mask Indonesian & international phone numbers (e.g. 0812-3456-7890, +62 812 3456 789, (022) 4231234, 8+ digit sequences)
        $phonePatterns = [
            '/(?:\+62|62|0)[\s.\-]?\d{2,4}(?:[\s.\-]?\d{2,4}){2,3}\b/',
            '/\b\d{9,15}\b/',
        ];

        foreach ($phonePatterns as $pattern) {
            if (preg_match($pattern, $cleaned) === 1) {
                $redacted = true;
                $cleaned = (string) preg_replace($pattern, self::REDACTION_PLACEHOLDER, $cleaned);
            }
        }

        // Collapse duplicate redaction placeholders or extra whitespace
        $cleaned = (string) preg_replace('/\s+/', ' ', $cleaned);

        return [
            'clean_text' => trim($cleaned),
            'redacted' => $redacted,
        ];
    }

    /**
     * Build a privacy-safe alias for the Blood Seeker (family representative) when shown to the volunteer Donor.
     */
    public function maskSeekerAlias(?User $seeker): string
    {
        $name = trim((string) ($seeker?->name ?? ''));
        if ($name === '') {
            return 'Perwakilan Keluarga Pasien';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $masked = collect($parts)->map(function (string $word, int $idx): string {
            if ($idx === 0 && mb_strlen($word) > 2) {
                return mb_substr($word, 0, 2).str_repeat('*', min(4, mb_strlen($word) - 2));
            }

            return mb_substr($word, 0, 1).'.';
        })->implode(' ');

        return 'Perwakilan Keluarga ('.$masked.')';
    }

    /**
     * Role-specific quick contextual message presets for one-tap hospital coordination.
     *
     * @return array<string, array{label: string, body: string, type: string}>
     */
    public function presetsForRole(string $role): array
    {
        if ($role === 'pendonor') {
            return [
                'on_the_way' => [
                    'label' => 'Sedang Menuju Faskes',
                    'body' => 'Saya sedang dalam perjalanan menuju instalasi Bank Darah (BDRS) rumah sakit rujukan.',
                    'type' => 'status_update',
                ],
                'arriving_15m' => [
                    'label' => 'Tiba ±15 Menit Lagi',
                    'body' => 'Estimasi sekitar 15 menit lagi saya tiba di area lobi / loket registrasi Bank Darah.',
                    'type' => 'status_update',
                ],
                'at_bdrs_desk' => [
                    'label' => 'Sudah di Loket BDRS',
                    'body' => 'Saya sudah berada di loket BDRS dan menyampaikan kode permohonan beserta token verifikasi kehadiran.',
                    'type' => 'arrival_check',
                ],
                'in_pre_screening' => [
                    'label' => 'Sedang Cek Tensi & Hb',
                    'body' => 'Saat ini saya sedang menjalani pemeriksaan fisik pra-donor (tensi darah & kadar Hb) bersama petugas.',
                    'type' => 'status_update',
                ],
            ];
        }

        return [
            'standby_at_desk' => [
                'label' => 'Pendamping Siaga di Loket',
                'body' => 'Perwakilan keluarga pasien sudah menunggu di ruang tunggu dekat loket registrasi BDRS.',
                'type' => 'status_update',
            ],
            'officer_informed' => [
                'label' => 'Petugas BDRS Sudah Diinfokan',
                'body' => 'Petugas jaga BDRS sudah kami informasikan mengenai kode kasus dan token verifikasi kehadiran Anda.',
                'type' => 'arrival_check',
            ],
            'direct_to_counter' => [
                'label' => 'Langsung ke Loket Registrasi',
                'body' => 'Setibanya di rumah sakit, Anda dapat langsung menuju loket BDRS tanpa perlu menunggu di lobi depan.',
                'type' => 'contextual_note',
            ],
            'gratitude_note' => [
                'label' => 'Ucapan Terima Kasih',
                'body' => 'Terima kasih banyak atas kesediaan donor darurat Anda. Semoga perjalanan menuju rumah sakit lancar dan aman.',
                'type' => 'contextual_note',
            ],
        ];
    }
}
