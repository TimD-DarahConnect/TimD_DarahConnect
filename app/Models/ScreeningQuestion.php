<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScreeningQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_code',
        'sort_order',
        'section_key',
        'section_title',
        'question_text',
        'expected_answer',
        'deferral_note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'expected_answer' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public static function ensureCatalogExists(): void
    {
        if (self::query()->count() >= 20) {
            return;
        }

        foreach (self::standardPmiCatalog() as $item) {
            self::updateOrCreate(
                ['question_code' => $item['question_code']],
                $item
            );
        }
    }

    /**
     * Standard 20-question pre-donation anamnesis catalog adapted from:
     * - Permenkes RI No. 91 Tahun 2015 tentang Standar Pelayanan Transfusi Darah (Bab II Seleksi Donor)
     * - Formulir Kuesioner & Informed Consent Seleksi Pendonor Unit Donor Darah (UDD) PMI
     * - WHO Blood Donor Selection Guidelines
     *
     * @return array<int, array<string, mixed>>
     */
    public static function standardPmiCatalog(): array
    {
        return [
            // Tahap 1: Kesiapan Fisik & Kebugaran Hari Ini (Jawaban Layak = YA / true)
            [
                'question_code' => 'ANM-01',
                'sort_order' => 1,
                'section_key' => 'readiness',
                'section_title' => 'Kesiapan Fisik & Kebugaran Hari Ini',
                'question_text' => 'Apakah Anda merasa sehat jasmani dan bugar pada hari ini (bebas dari keluhan demam, pusing, batuk, pilek, atau sesak napas)?',
                'expected_answer' => true,
                'deferral_note' => 'Calon pendonor wajib berada dalam kondisi sehat dan bugar tanpa gejala infeksi akut pada hari pendonoran.',
                'is_active' => true,
            ],
            [
                'question_code' => 'ANM-02',
                'sort_order' => 2,
                'section_key' => 'readiness',
                'section_title' => 'Kesiapan Fisik & Kebugaran Hari Ini',
                'question_text' => 'Apakah Anda berusia antara 17 hingga 60 tahun serta memiliki berat badan minimal 45 kilogram?',
                'expected_answer' => true,
                'deferral_note' => 'Sesuai Permenkes No. 91 Tahun 2015, syarat usia donor adalah 17–60 tahun dengan berat badan minimal 45 kg demi keamanan sirkulasi darah.',
                'is_active' => true,
            ],
            [
                'question_code' => 'ANM-03',
                'sort_order' => 3,
                'section_key' => 'readiness',
                'section_title' => 'Kesiapan Fisik & Kebugaran Hari Ini',
                'question_text' => 'Apakah Anda tidur cukup minimal 4–5 jam pada malam sebelum jadwal pelayanan donor darah?',
                'expected_answer' => true,
                'deferral_note' => 'Kurang tidur (< 4 jam) meningkatkan risiko reaksi vasovagal (pusing atau pingsan) saat maupun sesudah pengambilan darah.',
                'is_active' => true,
            ],
            [
                'question_code' => 'ANM-04',
                'sort_order' => 4,
                'section_key' => 'readiness',
                'section_title' => 'Kesiapan Fisik & Kebugaran Hari Ini',
                'question_text' => 'Apakah Anda sudah atau bersedia makan serta minum air putih yang cukup dalam 3–4 jam sebelum pengambilan darah?',
                'expected_answer' => true,
                'deferral_note' => 'Pendonor tidak dianjurkan mendonorkan darah dalam kondisi perut kosong guna mencegah hipoglikemia dan dehidrasi.',
                'is_active' => true,
            ],
            [
                'question_code' => 'ANM-05',
                'sort_order' => 5,
                'section_key' => 'readiness',
                'section_title' => 'Kesiapan Fisik & Kebugaran Hari Ini',
                'question_text' => 'Apakah jarak sejak donor darah lengkap (Whole Blood) terakhir Anda sudah mencapai minimal 60 hari (2 bulan), atau ini merupakan donor pertama Anda?',
                'expected_answer' => true,
                'deferral_note' => 'Interval minimal antar-donasi darah lengkap adalah 60 hari (8–12 minggu) agar cadangan zat besi dan eritrosit pulih optimal.',
                'is_active' => true,
            ],

            // Tahap 2: Riwayat Pengobatan, Vaksinasi & Tindakan Medis (Jawaban Layak = TIDAK / false)
            [
                'question_code' => 'ANM-06',
                'sort_order' => 6,
                'section_key' => 'medication_procedure',
                'section_title' => 'Riwayat Obat, Vaksinasi & Tindakan Medis',
                'question_text' => 'Apakah dalam 3 hari terakhir Anda sedang atau baru selesai mengonsumsi obat antibiotik atau obat infeksi lainnya?',
                'expected_answer' => false,
                'deferral_note' => 'Konsumsi antibiotik menandakan infeksi yang belum tuntas; donor ditunda minimal hingga 3–7 hari bebas obat dan sembuh sempurna.',
                'is_active' => true,
            ],
            [
                'question_code' => 'ANM-07',
                'sort_order' => 7,
                'section_key' => 'medication_procedure',
                'section_title' => 'Riwayat Obat, Vaksinasi & Tindakan Medis',
                'question_text' => 'Apakah dalam 7 hari terakhir Anda mengonsumsi aspirin, piroksikam, atau obat pengencer darah / antiplatelet?',
                'expected_answer' => false,
                'deferral_note' => 'Aspirin dan antiplatelet menghambat fungsi trombosit serta memperpanjang waktu pembekuan darah pada area tusukan vena.',
                'is_active' => true,
            ],
            [
                'question_code' => 'ANM-08',
                'sort_order' => 8,
                'section_key' => 'medication_procedure',
                'section_title' => 'Riwayat Obat, Vaksinasi & Tindakan Medis',
                'question_text' => 'Apakah dalam 6 minggu terakhir Anda mengalami demam tinggi (> 38°C), gejala demam berdarah (DBD), tifus, atau infeksi akut berat?',
                'expected_answer' => false,
                'deferral_note' => 'Riwayat demam akut, DBD, atau tifus dalam 6 minggu terakhir memerlukan masa pemulihan klinis sebelum mendonorkan darah.',
                'is_active' => true,
            ],
            [
                'question_code' => 'ANM-09',
                'sort_order' => 9,
                'section_key' => 'medication_procedure',
                'section_title' => 'Riwayat Obat, Vaksinasi & Tindakan Medis',
                'question_text' => 'Apakah dalam 7 hari terakhir Anda menjalani cabut gigi / tindakan bedah mulut kecil, atau menjalani operasi besar dalam 6 bulan terakhir?',
                'expected_answer' => false,
                'deferral_note' => 'Tindakan pencabutan gigi baru berisiko menimbulkan bakteremia sementara, sedangkan pasca-operasi besar memerlukan pemulihan jaringan.',
                'is_active' => true,
            ],
            [
                'question_code' => 'ANM-10',
                'sort_order' => 10,
                'section_key' => 'medication_procedure',
                'section_title' => 'Riwayat Obat, Vaksinasi & Tindakan Medis',
                'question_text' => 'Apakah dalam 4 minggu terakhir Anda baru menerima vaksinasi virus/bakteri hidup yang dilemahkan (seperti MMR, Varisela, Demam Kuning, atau Polio Oral)?',
                'expected_answer' => false,
                'deferral_note' => 'Penerima vaksin hidup yang dilemahkan perlu menunda donor darah selama 4 minggu demi keamanan pasien resipien imunokompromais.',
                'is_active' => true,
            ],

            // Tahap 3: Riwayat Penyakit Kronis & Kondisi Fisiologis (Jawaban Layak = TIDAK / false)
            [
                'question_code' => 'ANM-11',
                'sort_order' => 11,
                'section_key' => 'chronic_physiological',
                'section_title' => 'Riwayat Penyakit Kronis & Kondisi Fisiologis',
                'question_text' => 'Apakah Anda pernah atau sedang didiagnosis menderita penyakit jantung koroner, gagal jantung, gangguan irama jantung berat, atau stroke?',
                'expected_answer' => false,
                'deferral_note' => 'Riwayat penyakit kardiovaskular atau stroke merupakan kontraindikasi demi menjaga stabilitas hemodinamik pendonor.',
                'is_active' => true,
            ],
            [
                'question_code' => 'ANM-12',
                'sort_order' => 12,
                'section_key' => 'chronic_physiological',
                'section_title' => 'Riwayat Penyakit Kronis & Kondisi Fisiologis',
                'question_text' => 'Apakah Anda menderita penyakit paru kronis aktif (seperti TBC dalam pengobatan atau serangan asma aktif), epilepsi/kejang berulang, atau kelainan pembekuan darah?',
                'expected_answer' => false,
                'deferral_note' => 'Kondisi paru aktif, riwayat kejang epilepsi, atau gangguan koagulasi berisiko menimbulkan komplikasi medis saat flebotomi.',
                'is_active' => true,
            ],
            [
                'question_code' => 'ANM-13',
                'sort_order' => 13,
                'section_key' => 'chronic_physiological',
                'section_title' => 'Riwayat Penyakit Kronis & Kondisi Fisiologis',
                'question_text' => 'Apakah Anda menderita diabetes melitus yang menggunakan terapi suntik insulin, gagal ginjal kronis, atau riwayat penyakit keganasan (kanker)?',
                'expected_answer' => false,
                'deferral_note' => 'Penggunaan terapi insulin suntik, gangguan fungsi ginjal kronis, atau keganasan tidak memenuhi kriteria kelayakan medis UDD PMI.',
                'is_active' => true,
            ],
            [
                'question_code' => 'ANM-14',
                'sort_order' => 14,
                'section_key' => 'chronic_physiological',
                'section_title' => 'Riwayat Penyakit Kronis & Kondisi Fisiologis',
                'question_text' => 'Apakah dalam 12 bulan terakhir Anda pernah menerima transfusi darah/komponen darah atau menjalani transplantasi organ/jaringan?',
                'expected_answer' => false,
                'deferral_note' => 'Penerima transfusi darah wajib menunda donor minimal 12 bulan untuk melewati masa jendela (window period) pengawasan infeksi.',
                'is_active' => true,
            ],
            [
                'question_code' => 'ANM-15',
                'sort_order' => 15,
                'section_key' => 'chronic_physiological',
                'section_title' => 'Riwayat Penyakit Kronis & Kondisi Fisiologis',
                'question_text' => '(Khusus Wanita — pilih "Tidak" bila Anda Pria) Apakah saat ini Anda sedang hamil, menyusui eksklusif, dalam masa nifas (< 6 bulan pascapersalinan), atau sedang menstruasi hari deras?',
                'expected_answer' => false,
                'deferral_note' => 'Pendonor wanita yang sedang hamil, menyusui, nifas, atau menstruasi deras ditunda sementara untuk menjaga kadar hemoglobin dan zat besi.',
                'is_active' => true,
            ],

            // Tahap 4: Keamanan Transfusi & Pencegahan Penularan IMLTD (Jawaban Layak = TIDAK / false)
            [
                'question_code' => 'ANM-16',
                'sort_order' => 16,
                'section_key' => 'imltd_safety',
                'section_title' => 'Keamanan Transfusi & Pencegahan IMLTD',
                'question_text' => 'Apakah Anda pernah didiagnosis atau memperoleh hasil laboratorium reaktif terhadap Hepatitis B, Hepatitis C, HIV/AIDS, atau Sifilis?',
                'expected_answer' => false,
                'deferral_note' => 'Riwayat Infeksi Menular Lewat Transfusi Darah (Hepatitis B/C, HIV, Sifilis) merupakan kriteria penolakan demi keamanan pasien penerima darah.',
                'is_active' => true,
            ],
            [
                'question_code' => 'ANM-17',
                'sort_order' => 17,
                'section_key' => 'imltd_safety',
                'section_title' => 'Keamanan Transfusi & Pencegahan IMLTD',
                'question_text' => 'Apakah dalam 6–12 bulan terakhir Anda membuat tato baru, tindik tubuh/telinga non-medis, akupunktur, atau bekam pisau dengan alat yang belum terjamin sterilitas sekali pakainya?',
                'expected_answer' => false,
                'deferral_note' => 'Tindakan perlukaan kulit (tato, tindik, bekam basah) dalam 6–12 bulan terakhir memerlukan masa tunda penapisan periode jendela IMLTD.',
                'is_active' => true,
            ],
            [
                'question_code' => 'ANM-18',
                'sort_order' => 18,
                'section_key' => 'imltd_safety',
                'section_title' => 'Keamanan Transfusi & Pencegahan IMLTD',
                'question_text' => 'Apakah dalam 12 bulan terakhir Anda pernah menderita penyakit Malaria atau mengalami gejala demam setelah berkunjung ke wilayah endemis tinggi Malaria?',
                'expected_answer' => false,
                'deferral_note' => 'Riwayat Malaria atau demam pasca-perjalanan ke wilayah endemis memerlukan penundaan sementara untuk mencegah transmisi plasmodium.',
                'is_active' => true,
            ],
            [
                'question_code' => 'ANM-19',
                'sort_order' => 19,
                'section_key' => 'imltd_safety',
                'section_title' => 'Keamanan Transfusi & Pencegahan IMLTD',
                'question_text' => 'Apakah dalam 12 bulan terakhir Anda pernah mengalami luka tusuk jarum suntik medis secara tidak sengaja atau kontak darah terbuka dengan penderita Hepatitis/HIV?',
                'expected_answer' => false,
                'deferral_note' => 'Paparan kontak darah terbuka atau luka tusuk jarum suntik memerlukan masa tunda observasi minimal 6–12 bulan.',
                'is_active' => true,
            ],
            [
                'question_code' => 'ANM-20',
                'sort_order' => 20,
                'section_key' => 'imltd_safety',
                'section_title' => 'Keamanan Transfusi & Pencegahan IMLTD',
                'question_text' => 'Apakah Anda pernah menggunakan narkotika/obat suntik tanpa indikasi dokter atau memiliki perilaku berisiko tinggi penularan infeksi menular melalui darah?',
                'expected_answer' => false,
                'deferral_note' => 'Riwayat penggunaan jarum suntik non-medis atau paparan risiko tinggi IMLTD tidak memenuhi standar keamanan darah Permenkes No. 91 Tahun 2015.',
                'is_active' => true,
            ],
        ];
    }
}
