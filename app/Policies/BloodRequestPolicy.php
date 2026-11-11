<?php

namespace App\Policies;

use App\Models\BloodRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BloodRequestPolicy
{
    /**
     * Check whether the authenticated seeker is the creator of the blood request.
     */
    public function owns(User $user, BloodRequest $bloodRequest): bool
    {
        return $user->isSeeker() && (int) $bloodRequest->requester_id === (int) $user->id;
    }

    /**
     * Determine whether the seeker can view their own emergency blood request detail.
     */
    public function view(User $user, BloodRequest $bloodRequest): Response
    {
        return $this->owns($user, $bloodRequest)
            ? Response::allow()
            : Response::deny('Anda hanya dapat mengakses rincian permohonan yang Anda ajukan sendiri.');
    }

    /**
     * Determine whether the seeker can edit the blood request before medical verification locks it.
     */
    public function update(User $user, BloodRequest $bloodRequest): Response
    {
        if (! $this->owns($user, $bloodRequest)) {
            return Response::deny('Anda hanya dapat memperbarui permohonan darah yang Anda ajukan sendiri.');
        }

        if (! $bloodRequest->isEditableByRequester()) {
            return Response::deny('Permohonan darah ini sudah diverifikasi atau ditutup sehingga rinciannya telah dikunci.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can access the confidential doctor referral letter.
     */
    public function viewReferralLetter(User $user, BloodRequest $bloodRequest): Response
    {
        $isOwner = $this->owns($user, $bloodRequest);
        $isAuthorizedStaff = $user->isUddStaff() || $user->isHospitalStaff() || $user->isAdmin();

        return ($isOwner || $isAuthorizedStaff)
            ? Response::allow()
            : Response::deny('Dokumen medis pasien bersifat rahasia dan hanya dapat diakses oleh pihak berwenang.');
    }
}
