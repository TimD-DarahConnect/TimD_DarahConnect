<?php

namespace App\Services\DonorDiscovery;

use App\Enums\BloodComponent;
use App\Enums\BloodType;
use App\Enums\Rhesus;
use App\Models\BloodRequest;
use Illuminate\Database\Eloquent\Builder;

class TransfusionCompatibilityService
{
    /**
     * Determine compatible recipient ABO blood types for a given donor ABO and blood component.
     *
     * Clinical basis:
     * - PRC (Packed Red Cells): Cellular compatibility (O is universal red-cell donor; AB receives all ABO).
     * - WB (Whole Blood): Contains both red cells and high-titer plasma; requires ABO-identical transfusion.
     * - FFP (Fresh Frozen Plasma): Plasma antibody compatibility (AB lacks anti-A/anti-B -> universal plasma donor).
     * - TC (Thrombocyte Concentrate): Platelets suspended in donor plasma; ABO-identical or plasma-compatible.
     *
     * @return array<int, string>
     */
    public function compatibleRecipientBloodTypes(BloodType|string $donorBloodType, BloodComponent|string $component): array
    {
        $abo = $donorBloodType instanceof BloodType ? $donorBloodType->value : strtoupper($donorBloodType);
        $comp = $component instanceof BloodComponent ? $component : BloodComponent::tryFrom(strtoupper($component));

        if (! in_array($abo, BloodType::values(), true) || $comp === null) {
            return [];
        }

        return match ($comp) {
            BloodComponent::PRC => match ($abo) {
                'O' => ['O', 'A', 'B', 'AB'],
                'A' => ['A', 'AB'],
                'B' => ['B', 'AB'],
                'AB' => ['AB'],
            },
            BloodComponent::WB => [$abo],
            BloodComponent::FFP, BloodComponent::TC => match ($abo) {
                'AB' => ['AB', 'A', 'B', 'O'],
                'A' => ['A', 'O'],
                'B' => ['B', 'O'],
                'O' => ['O'],
            },
        };
    }

    /**
     * Determine compatible recipient Rhesus factors for a given donor Rhesus factor.
     *
     * Rh-negative donors lack D antigen and can safely support Rh-negative and Rh-positive recipients,
     * whereas Rh-positive donors are matched strictly to Rh-positive recipients to prevent anti-D sensitization.
     *
     * @return array<int, string>
     */
    public function compatibleRecipientRhesus(Rhesus|string $donorRhesus, BloodComponent|string|null $component = null): array
    {
        $rh = $donorRhesus instanceof Rhesus ? $donorRhesus->value : $donorRhesus;

        return match ($rh) {
            '-' => ['-', '+'],
            '+' => ['+'],
            default => [],
        };
    }

    /**
     * Build a structured compatibility matrix for a donor blood group across all (or one) blood components.
     *
     * @return array<string, array{abo: array<int, string>, rhesus: array<int, string>}>
     */
    public function buildRecipientMatrixForDonor(
        BloodType|string|null $donorBloodType,
        Rhesus|string|null $donorRhesus,
        BloodComponent|string|null $onlyComponent = null
    ): array {
        $components = $onlyComponent !== null && $onlyComponent !== ''
            ? array_filter([
                $onlyComponent instanceof BloodComponent
                    ? $onlyComponent
                    : BloodComponent::tryFrom(strtoupper((string) $onlyComponent)),
            ])
            : BloodComponent::cases();

        $hasAbo = $donorBloodType !== null && $donorBloodType !== '';
        $hasRhesus = $donorRhesus !== null && $donorRhesus !== '';

        $matrix = [];

        foreach ($components as $component) {
            $aboList = $hasAbo
                ? $this->compatibleRecipientBloodTypes($donorBloodType, $component)
                : BloodType::values();

            $rhList = $hasRhesus
                ? $this->compatibleRecipientRhesus($donorRhesus, $component)
                : Rhesus::values();

            if ($aboList !== [] && $rhList !== []) {
                $matrix[$component->value] = [
                    'abo' => $aboList,
                    'rhesus' => $rhList,
                ];
            }
        }

        return $matrix;
    }

    /**
     * Constrain an Eloquent BloodRequest query to requests that a donor with the given ABO/Rhesus can medically fulfill.
     */
    public function applyDonorCompatibilityScope(
        Builder $query,
        BloodType|string|null $donorBloodType,
        Rhesus|string|null $donorRhesus,
        BloodComponent|string|null $onlyComponent = null
    ): Builder {
        $matrix = $this->buildRecipientMatrixForDonor($donorBloodType, $donorRhesus, $onlyComponent);

        if ($matrix === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $outer) use ($matrix): void {
            foreach ($matrix as $componentCode => $allowed) {
                $outer->orWhere(function (Builder $branch) use ($componentCode, $allowed): void {
                    $branch->where('component_type', $componentCode)
                        ->whereIn('blood_type', $allowed['abo'])
                        ->whereIn('rhesus', $allowed['rhesus']);
                });
            }
        });
    }

    /**
     * Evaluate whether a donor with given ABO & Rhesus is compatible with a specific BloodRequest,
     * returning clinical classification, tier score, and Indonesian medical explanation.
     *
     * @return array{compatible: bool, exact_match: bool, tier: string, badge_label: string, pill_class: string, explanation: string}
     */
    public function evaluateForRequest(
        BloodType|string|null $donorBloodType,
        Rhesus|string|null $donorRhesus,
        BloodRequest $bloodRequest
    ): array {
        $donorAbo = $donorBloodType instanceof BloodType ? $donorBloodType->value : (string) $donorBloodType;
        $donorRh = $donorRhesus instanceof Rhesus ? $donorRhesus->value : (string) $donorRhesus;

        if (! in_array($donorAbo, BloodType::values(), true) || ! in_array($donorRh, Rhesus::values(), true)) {
            return [
                'compatible' => false,
                'exact_match' => false,
                'tier' => 'unknown',
                'badge_label' => 'Profil Golongan Belum Lengkap',
                'pill_class' => 'dc-pill-neutral',
                'explanation' => 'Lengkapi golongan darah ABO dan Rhesus Anda untuk melihat analisis kecocokan transfusi.',
            ];
        }

        $recipientAbo = $bloodRequest->blood_type instanceof BloodType
            ? $bloodRequest->blood_type->value
            : (string) $bloodRequest->blood_type;
        $recipientRh = $bloodRequest->rhesus instanceof Rhesus
            ? $bloodRequest->rhesus->value
            : (string) $bloodRequest->rhesus;
        $component = $bloodRequest->component_type instanceof BloodComponent
            ? $bloodRequest->component_type
            : (BloodComponent::tryFrom((string) $bloodRequest->component_type) ?? BloodComponent::PRC);

        $allowedAbos = $this->compatibleRecipientBloodTypes($donorAbo, $component);
        $allowedRhesus = $this->compatibleRecipientRhesus($donorRh, $component);

        $isCompatible = in_array($recipientAbo, $allowedAbos, true)
            && in_array($recipientRh, $allowedRhesus, true);

        $isExact = $donorAbo === $recipientAbo && $donorRh === $recipientRh;
        $donorGroup = $donorAbo.$donorRh;
        $recipientGroup = $recipientAbo.$recipientRh;

        if ($isExact) {
            return [
                'compatible' => true,
                'exact_match' => true,
                'tier' => 'identical',
                'badge_label' => "Golongan Identik ({$donorGroup})",
                'pill_class' => 'dc-pill-success',
                'explanation' => "Golongan darah Anda ({$donorGroup}) identik secara ABO dan Rhesus untuk kebutuhan komponen {$component->value} pasien.",
            ];
        }

        if ($isCompatible) {
            return [
                'compatible' => true,
                'exact_match' => false,
                'tier' => 'cross_compatible',
                'badge_label' => "Kompatibel {$component->value} ({$donorGroup} → {$recipientGroup})",
                'pill_class' => 'dc-pill-warning',
                'explanation' => "Berdasarkan aturan transfusi komponen {$component->value}, pendonor {$donorGroup} kompatibel secara klinis untuk membantu resipien {$recipientGroup} (tetap melalui uji silang serasi BDRS).",
            ];
        }

        return [
            'compatible' => false,
            'exact_match' => false,
            'tier' => 'incompatible',
            'badge_label' => "Tidak Kompatibel ({$donorGroup} ↛ {$recipientGroup})",
            'pill_class' => 'dc-pill-critical',
            'explanation' => "Untuk komponen {$component->value}, darah pendonor {$donorGroup} tidak kompatibel secara serologis bagi pasien bergolongan {$recipientGroup}.",
        ];
    }

    /**
     * Summarize compatible recipient blood groups for display in the active filter banner.
     */
    public function summarizeDonorReach(
        BloodType|string|null $donorBloodType,
        Rhesus|string|null $donorRhesus,
        BloodComponent|string|null $onlyComponent = null
    ): string {
        $matrix = $this->buildRecipientMatrixForDonor($donorBloodType, $donorRhesus, $onlyComponent);
        if ($matrix === []) {
            return '-';
        }

        $segments = [];
        foreach ($matrix as $compCode => $allowed) {
            $groups = [];
            foreach ($allowed['abo'] as $abo) {
                foreach ($allowed['rhesus'] as $rh) {
                    $groups[] = $abo.$rh;
                }
            }
            $segments[] = $compCode.': '.implode(', ', $groups);
        }

        return implode(' · ', $segments);
    }
}
