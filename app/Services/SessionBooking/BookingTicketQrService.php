<?php

namespace App\Services\SessionBooking;

use App\Models\DonorBooking;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BookingTicketQrService
{
    /**
     * Build the structured fast-track check-in payload encoded inside the donor's digital QR pass.
     */
    public function buildPayload(DonorBooking $booking): string
    {
        return (string) json_encode([
            'v' => 1,
            'type' => 'DONOR_BOOKING',
            'code' => $booking->booking_code,
            'token' => $booking->qr_token,
            'session_id' => (int) $booking->donation_session_id,
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate a crisp vector SVG QR Code without requiring GD or Imagick PHP extensions.
     */
    public function generateSvg(DonorBooking $booking, int $size = 216): string
    {
        $payload = $this->buildPayload($booking);

        if (class_exists(QrCode::class)) {
            $rendered = QrCode::format('svg')
                ->size($size)
                ->margin(1)
                ->errorCorrection('M')
                ->color(15, 23, 42)
                ->backgroundColor(255, 255, 255)
                ->generate($payload);

            return trim((string) $rendered);
        }

        if (class_exists(Writer::class)) {
            $renderer = new ImageRenderer(
                new RendererStyle($size, 1),
                new SvgImageBackEnd
            );

            return trim((new Writer($renderer))->writeString($payload));
        }

        return '';
    }
}
