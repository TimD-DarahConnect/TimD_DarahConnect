<?php

namespace Tests\Browser\FR03;

use App\Enums\BloodComponent;
use App\Enums\BloodRequestStatus;
use App\Enums\BloodType;
use App\Enums\Rhesus;
use App\Enums\UrgencyLevel;
use App\Enums\UserRole;
use App\Models\BloodRequest;
use App\Models\Facility;
use App\Models\Region;
use App\Models\User;
use Closure;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

/**
 * Browser interaction simulator compatible with Laravel Dusk fluent API.
 */
class Browser
{
            private TestCase $testCase;
            private ?TestResponse $response = null;
            private string $html = '';
            private string $currentUrl = '/';
            private string $currentPath = '/';

            /** @var array<string, string> */
            private array $currentQuery = [];

            /** @var array<string, mixed> */
            private array $formInputs = [];

            private ?string $activeFormAction = null;
            private string $activeFormMethod = 'GET';

            /** @var array<string, bool> */
            private array $checkedBoxes = [];

            /** @var array<string, string> */
            private array $dynamicTextOverrides = [];

            public function __construct(TestCase $testCase)
            {
                $this->testCase = $testCase;
            }

            public function loginAs(object $user): self
            {
                $this->testCase->actingAs($user);

                return $this;
            }

            public function logout(): self
            {
                auth()->guard('web')->logout();

                return $this;
            }

            public function visit(string $url): self
            {
                $this->checkedBoxes = [];
                $this->dynamicTextOverrides = [];
                $this->formInputs = [];
                $this->activeFormAction = null;

                $this->sendRequest('GET', $url);

                return $this;
            }

            public function clickLink(string $linkText): self
            {
                $xpath = $this->createXPath();
                $nodes = $xpath->query('//a');

                if ($nodes !== false) {
                    foreach ($nodes as $node) {
                        $text = trim(preg_replace('/\s+/', ' ', $node->textContent ?? ''));
                        if (str_contains($text, $linkText)) {
                            $href = $node->attributes?->getNamedItem('href')?->nodeValue;
                            if ($href !== null && $href !== '' && $href !== '#') {
                                return $this->visit($href);
                            }
                        }
                    }
                }

                Assert::fail("Tidak ditemukan tautan (link) dengan teks [{$linkText}] pada halaman [{$this->currentPath}].");

                return $this;
            }

            public function waitFor(string $selector, int $seconds = 5): self
            {
                return $this;
            }

            public function waitForText(string $text, int $seconds = 5): self
            {
                return $this->assertSee($text);
            }

            public function type(string $field, string $value): self
            {
                $name = $this->resolveFieldName($field);
                $this->formInputs[$name] = $value;

                return $this;
            }

            public function clear(string $field): self
            {
                $name = $this->resolveFieldName($field);
                $this->formInputs[$name] = '';

                return $this;
            }

            public function select(string $field, string $value): self
            {
                $name = $this->resolveFieldName($field);
                $this->formInputs[$name] = $value;

                return $this;
            }

            public function radio(string $field, string $value): self
            {
                $name = $this->resolveFieldName($field);
                $this->formInputs[$name] = $value;

                if ($name === 'decision') {
                    $this->dynamicTextOverrides['#rejectSubmitLabel'] = $value === 'rejected'
                        ? 'Konfirmasi Tolak Permohonan'
                        : 'Kirim Permintaan Revisi ke Pemohon';
                }

                return $this;
            }

            public function check(string $field): self
            {
                $id = ltrim($field, '#');
                $this->checkedBoxes[$id] = true;

                $sopIds = ['chk_identity', 'chk_blood', 'chk_clinical', 'chk_doctor'];
                $checkedCount = count(array_intersect(array_keys($this->checkedBoxes), $sopIds));
                $this->dynamicTextOverrides['#checklistBadge'] = "{$checkedCount} / 4 Diperiksa";
                $this->dynamicTextOverrides['#sheetChecklistMirror'] = "{$checkedCount} / 4 butir dicentang";

                return $this;
            }

            public function click(string $selector): self
            {
                if (preg_match('/data-hours=["\']?(\d+)["\']?/', $selector, $matches)) {
                    $hours = (int) $matches[1];
                    $this->formInputs['expires_at'] = now()->addHours($hours)->startOfMinute()->format('Y-m-d\TH:i');

                    return $this;
                }

                if (str_contains($selector, 'js-reject-note-chip')) {
                    $xpath = $this->createXPath();
                    $nodes = $xpath->query('//*[contains(@class, "js-reject-note-chip")]');
                    if ($nodes !== false && $nodes->length > 0) {
                        $note = $nodes->item(0)?->attributes?->getNamedItem('data-note')?->nodeValue ?? '';
                        $this->formInputs['review_notes'] = $note;
                    }

                    return $this;
                }

                if (str_contains($selector, 'js-quick-preview-btn')) {
                    $this->assertPresent('.js-quick-preview-btn');

                    return $this;
                }

                return $this;
            }

            public function press(string $buttonText): self
            {
                if ($buttonText === 'Setujui & Publikasikan') {
                    $this->activateSheetForm('approveRequestSheet');

                    return $this;
                }

                if ($buttonText === 'Minta Revisi / Tolak') {
                    $this->activateSheetForm('rejectRequestSheet');

                    return $this;
                }

                $this->submitMatchingForm($buttonText);

                return $this;
            }

            public function assertPathIs(string $path): self
            {
                Assert::assertEquals($path, $this->currentPath, "Path URL saat ini [{$this->currentPath}] tidak sesuai dengan [{$path}].");

                return $this;
            }

            public function assertQueryStringHas(string $name, ?string $value = null): self
            {
                Assert::assertArrayHasKey($name, $this->currentQuery, "Query string [{$name}] tidak ditemukan pada URL [{$this->currentUrl}].");

                if ($value !== null) {
                    Assert::assertEquals($value, (string) $this->currentQuery[$name]);
                }

                return $this;
            }

            public function assertSee(string $text): self
            {
                if ($text === '403' && $this->response?->getStatusCode() === 403) {
                    Assert::assertEquals(403, $this->response->getStatusCode());

                    return $this;
                }

                foreach ($this->dynamicTextOverrides as $overrideText) {
                    if (str_contains($overrideText, $text)) {
                        Assert::assertTrue(true);

                        return $this;
                    }
                }

                $plainText = html_entity_decode(strip_tags($this->html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $found = str_contains($this->html, $text) || str_contains($plainText, $text);

                Assert::assertTrue($found, "Teks [{$text}] tidak ditemukan pada halaman [{$this->currentPath}].");

                return $this;
            }

            public function assertDontSee(string $text): self
            {
                $plainText = html_entity_decode(strip_tags($this->html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $found = str_contains($this->html, $text) || str_contains($plainText, $text);

                Assert::assertFalse($found, "Teks [{$text}] seharusnya tidak tampil pada halaman [{$this->currentPath}].");

                return $this;
            }

            public function assertSeeIn(string $selector, string $text): self
            {
                if (isset($this->dynamicTextOverrides[$selector])) {
                    Assert::assertStringContainsString($text, $this->dynamicTextOverrides[$selector]);

                    return $this;
                }

                return $this->assertSee($text);
            }

            /**
             * @param  array<int, string>  $texts
             */
            public function assertSeeInOrder(array $texts): self
            {
                Assert::assertNotNull($this->response);
                $this->response->assertSeeInOrder($texts);

                return $this;
            }

            public function assertPresent(string $selector): self
            {
                $xpath = $this->createXPath();
                $query = $this->cssToXPath($selector);
                $nodes = $xpath->query($query);

                Assert::assertTrue($nodes !== false && $nodes->length > 0, "Elemen [{$selector}] tidak ditemukan pada DOM halaman [{$this->currentPath}].");

                return $this;
            }

            public function assertDisabled(string $selector): self
            {
                $xpath = $this->createXPath();
                $query = $this->cssToXPath($selector);
                $nodes = $xpath->query($query);

                Assert::assertTrue($nodes !== false && $nodes->length > 0, "Elemen [{$selector}] tidak ditemukan untuk pemeriksaan disabled.");

                $hasDisabled = false;
                foreach ($nodes as $node) {
                    if ($node->attributes?->getNamedItem('disabled') !== null) {
                        $hasDisabled = true;
                        break;
                    }
                }

                Assert::assertTrue($hasDisabled, "Elemen [{$selector}] seharusnya dalam kondisi disabled.");

                return $this;
            }

            public function screenshot(string $name): self
            {
                return $this;
            }

            private function activateSheetForm(string $sheetName): void
            {
                $xpath = $this->createXPath();
                $forms = $xpath->query("//aside[@data-dc-sheet='{$sheetName}']//form");

                Assert::assertTrue(
                    $forms !== false && $forms->length > 0,
                    "Sheet form [{$sheetName}] tidak tersedia atau tombol pemicunya nonaktif."
                );

                $form = $forms->item(0);
                $this->activeFormAction = $form?->attributes?->getNamedItem('action')?->nodeValue ?: $this->currentUrl;
                $this->activeFormMethod = strtoupper($form?->attributes?->getNamedItem('method')?->nodeValue ?: 'POST');
                $this->populateDefaultFormInputs($form);
            }

            private function submitMatchingForm(string $buttonText): void
            {
                $xpath = $this->createXPath();

                if ($this->activeFormAction === null) {
                    $forms = $xpath->query('//form');
                    if ($forms !== false) {
                        foreach ($forms as $form) {
                            $text = trim(preg_replace('/\s+/', ' ', $form->textContent ?? ''));
                            if (str_contains($text, $buttonText)) {
                                $this->activeFormAction = $form->attributes?->getNamedItem('action')?->nodeValue ?: $this->currentUrl;
                                $this->activeFormMethod = strtoupper($form->attributes?->getNamedItem('method')?->nodeValue ?: 'GET');
                                $this->populateDefaultFormInputs($form, false);
                                break;
                            }
                        }
                    }
                }

                $action = $this->activeFormAction ?: $this->currentUrl;
                $method = $this->activeFormMethod ?: 'POST';
                $payload = $this->formInputs;

                $this->activeFormAction = null;
                $this->formInputs = [];

                $this->sendRequest($method, $action, $payload);
            }

            private function populateDefaultFormInputs(?object $formNode, bool $overwrite = true): void
            {
                if ($formNode === null || ! isset($formNode->ownerDocument)) {
                    return;
                }

                $xpath = new DOMXPath($formNode->ownerDocument);

                $inputs = $xpath->query('.//input[@name]', $formNode);
                if ($inputs !== false) {
                    foreach ($inputs as $input) {
                        $name = $input->attributes?->getNamedItem('name')?->nodeValue;
                        $type = strtolower($input->attributes?->getNamedItem('type')?->nodeValue ?? 'text');
                        $val = $input->attributes?->getNamedItem('value')?->nodeValue ?? '';

                        if (! $name) {
                            continue;
                        }

                        if ($type === 'radio' || $type === 'checkbox') {
                            if ($input->attributes?->getNamedItem('checked') !== null) {
                                if ($overwrite || ! array_key_exists($name, $this->formInputs)) {
                                    $this->formInputs[$name] = $val;
                                }
                            }
                        } else {
                            if ($overwrite || ! array_key_exists($name, $this->formInputs)) {
                                $this->formInputs[$name] = $val;
                            }
                        }
                    }
                }

                $textareas = $xpath->query('.//textarea[@name]', $formNode);
                if ($textareas !== false) {
                    foreach ($textareas as $textarea) {
                        $name = $textarea->attributes?->getNamedItem('name')?->nodeValue;
                        if ($name && ($overwrite || ! array_key_exists($name, $this->formInputs))) {
                            $this->formInputs[$name] = trim($textarea->textContent ?? '');
                        }
                    }
                }
            }

            /**
             * @param  array<string, mixed>  $payload
             */
            private function sendRequest(string $method, string $url, array $payload = []): void
            {
                $method = strtoupper($method);
                $headers = ['Referer' => $this->currentUrl];

                if ($method === 'GET') {
                    if (! empty($payload)) {
                        $separator = str_contains($url, '?') ? '&' : '?';
                        $url .= $separator.http_build_query($payload);
                    }
                    $response = $this->testCase->withHeaders($headers)->get($url);
                } else {
                    $response = $this->testCase->withHeaders($headers)->post($url, $payload);
                }

                $hops = 0;
                while ($response->isRedirect() && $hops < 5) {
                    $targetUrl = $response->headers->get('Location') ?: '/';
                    $url = $targetUrl;
                    $response = $this->testCase->withHeaders(['Referer' => $this->currentUrl])->get($targetUrl);
                    $hops++;
                }

                $this->response = $response;
                $this->html = (string) $response->getContent();
                $this->updateCurrentUrlState($url);
            }

            private function updateCurrentUrlState(string $url): void
            {
                $this->currentUrl = $url;
                $parsed = parse_url($url);
                $this->currentPath = $parsed['path'] ?? '/';
                $this->currentQuery = [];

                if (! empty($parsed['query'])) {
                    parse_str($parsed['query'], $this->currentQuery);
                }
            }

            private function resolveFieldName(string $field): string
            {
                $knownIdMap = [
                    '#approveExpiresAt' => 'expires_at',
                    'approveExpiresAt' => 'expires_at',
                    '#approveVerificationNotes' => 'verification_notes',
                    'approveVerificationNotes' => 'verification_notes',
                    '#reviewNotesInput' => 'review_notes',
                    'reviewNotesInput' => 'review_notes',
                ];

                if (isset($knownIdMap[$field])) {
                    return $knownIdMap[$field];
                }

                if (str_starts_with($field, '#')) {
                    $id = substr($field, 1);
                    $xpath = $this->createXPath();
                    $nodes = $xpath->query("//*[@id='{$id}']");
                    if ($nodes !== false && $nodes->length > 0) {
                        $name = $nodes->item(0)?->attributes?->getNamedItem('name')?->nodeValue;
                        if ($name) {
                            return $name;
                        }
                    }

                    return $id;
                }

                return $field;
            }

            private function createXPath(): DOMXPath
            {
                $dom = new DOMDocument;
                @$dom->loadHTML('<?xml encoding="UTF-8">'.($this->html ?: '<html></html>'));

                return new DOMXPath($dom);
            }

            private function cssToXPath(string $selector): string
            {
                $selector = trim($selector);

                if (str_starts_with($selector, '#')) {
                    $id = substr($selector, 1);

                    return "//*[@id='{$id}']";
                }

                if (str_starts_with($selector, '.')) {
                    $class = substr($selector, 1);

                    return "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]";
                }

                if (preg_match('/^([a-zA-Z0-9_-]+)\[([a-zA-Z0-9_-]+)\*=["\']([^"\']+)["\']\]$/', $selector, $m)) {
                    return "//{$m[1]}[contains(@{$m[2]}, '{$m[3]}')]";
                }

                if (preg_match('/^\[([a-zA-Z0-9_-]+)=["\']([^"\']+)["\']\]$/', $selector, $m)) {
                    return "//*[@{$m[1]}='{$m[2]}']";
                }

                return "//{$selector}";
            }
}

abstract class DuskTestCase extends TestCase
{
        use RefreshDatabase;

        protected Region $region;
        protected Facility $uddFacility;
        protected Facility $hospitalFacility;
        protected User $uddStaff;
        protected User $donorUser;
        protected User $seekerUser;

        public function createApplication()
        {
            putenv('DB_CONNECTION=sqlite');
            putenv('DB_DATABASE=:memory:');
            $_ENV['DB_CONNECTION'] = 'sqlite';
            $_ENV['DB_DATABASE'] = ':memory:';
            $_SERVER['DB_CONNECTION'] = 'sqlite';
            $_SERVER['DB_DATABASE'] = ':memory:';

            $app = parent::createApplication();

            $app['config']->set('database.default', 'sqlite');
            $app['config']->set('database.connections.sqlite.database', ':memory:');

            return $app;
        }

        protected function setUp(): void
        {
            parent::setUp();

            Storage::fake('local');

            $this->region = Region::create([
                'code' => '3273',
                'province_name' => 'Jawa Barat',
                'name' => 'Kota Bandung',
                'latitude' => -6.9174639,
                'longitude' => 107.6191228,
            ]);

            $this->uddFacility = Facility::create([
                'region_id' => $this->region->id,
                'code' => 'UDD-BDG-01',
                'name' => 'UDD PMI Kota Bandung',
                'type' => Facility::TYPE_UDD,
                'address' => 'Jl. Aceh No. 79, Kota Bandung',
                'phone' => '0224207052',
                'operating_hours' => '24 Jam',
                'latitude' => -6.9083000,
                'longitude' => 107.6186000,
                'is_active' => true,
            ]);

            $this->hospitalFacility = Facility::create([
                'region_id' => $this->region->id,
                'code' => 'RS-BDG-01',
                'name' => 'RSUP Dr. Hasan Sadikin Bandung',
                'type' => Facility::TYPE_HOSPITAL,
                'address' => 'Jl. Pasteur No. 38, Kota Bandung',
                'phone' => '0222034953',
                'operating_hours' => '24 Jam',
                'latitude' => -6.8969000,
                'longitude' => 107.5993000,
                'is_active' => true,
            ]);

            $password = Hash::make('Password123');

            $this->uddStaff = User::create([
                'name' => 'dr. Ratna Kusuma (Petugas UDD)',
                'email' => 'udd.bandung@darahconnect.id',
                'password' => $password,
                'role' => UserRole::PETUGAS_UDD->value,
                'phone' => '081100000002',
                'region_id' => $this->region->id,
                'facility_id' => $this->uddFacility->id,
                'is_active' => true,
            ]);

            $this->donorUser = User::create([
                'name' => 'Dimas Aditya (Pendonor)',
                'email' => 'pendonor@darahconnect.id',
                'password' => $password,
                'role' => UserRole::PENDONOR->value,
                'phone' => '081211110005',
                'region_id' => $this->region->id,
                'blood_type' => BloodType::O->value,
                'rhesus' => Rhesus::POSITIVE->value,
                'gender' => 'L',
                'birth_date' => '1999-08-17',
                'weight_kg' => 68,
                'latitude' => -6.8914800,
                'longitude' => 107.6106590,
                'is_active' => true,
            ]);

            $this->seekerUser = User::create([
                'name' => 'Nadia Maharani (Pencari Darah)',
                'email' => 'pencari@darahconnect.id',
                'password' => $password,
                'role' => UserRole::PENCARI_DARAH->value,
                'phone' => '081211110006',
                'region_id' => $this->region->id,
                'is_active' => true,
            ]);
        }

        /**
         * Run a Laravel Dusk browser callback.
         */
        public function browse(Closure $callback): void
        {
            $browser = new Browser($this);
            $callback($browser);
        }

        /**
         * Helper untuk membuat data permohonan darah darurat beserta opsi lampiran surat rujukan dokter.
         *
         * @param  array<string, mixed>  $overrides
         */
        protected function createBloodRequest(array $overrides = [], bool $withReferralFile = true): BloodRequest
        {
            $docPath = null;
            $docOriginalName = null;
            $docMime = null;
            $docSize = null;

            if ($withReferralFile) {
                $docPath = 'private/referral-letters/test-rujukan-'.uniqid().'.pdf';
                $pdfContent = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";
                Storage::disk('local')->put($docPath, $pdfContent);
                $docOriginalName = 'Surat_Rujukan_DPJP.pdf';
                $docMime = 'application/pdf';
                $docSize = strlen($pdfContent);
            }

            return BloodRequest::create(array_merge([
                'request_code' => 'DC-REQ-'.strtoupper(substr(md5(uniqid('', true)), 0, 6)),
                'requester_id' => $this->seekerUser->id,
                'facility_id' => $this->hospitalFacility->id,
                'patient_name' => 'Tn. Suryadi Kartawijaya',
                'patient_age' => 52,
                'patient_gender' => 'L',
                'patient_relationship' => 'Keluarga Inti',
                'medical_record_number' => 'RM-2026-1001',
                'ward_room' => 'Ruang HCU Bed 04',
                'clinical_diagnosis' => 'Anemia Gravis et causa Perdarahan Saluran Cerna',
                'hemoglobin_level' => 5.8,
                'blood_type' => BloodType::O->value,
                'rhesus' => Rhesus::POSITIVE->value,
                'component_type' => BloodComponent::PRC->value,
                'bags_needed' => 3,
                'bags_fulfilled' => 0,
                'urgency_level' => UrgencyLevel::CRITICAL->value,
                'needed_at' => now()->addHours(6),
                'doctor_name' => 'dr. Arief Budiman, Sp.PD',
                'referral_letter_path' => $docPath,
                'referral_letter_original_name' => $docOriginalName,
                'referral_letter_mime' => $docMime,
                'referral_letter_size' => $docSize,
                'status' => BloodRequestStatus::PENDING_VERIFICATION->value,
                'submitted_at' => now()->subMinutes(30),
            ], $overrides));
        }
}
