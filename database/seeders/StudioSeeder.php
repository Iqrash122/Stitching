<?php

namespace Database\Seeders;

use App\Models\BusinessSetting;
use App\Models\MeasurementTemplate;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudioSeeder extends Seeder
{
    public function run(): void
    {
        BusinessSetting::firstOrCreate(['id' => 1]);
        foreach (['ST', 'INV', 'PAY', 'CR', 'RF', 'OWNER'] as $kind) {
            DB::table('number_sequences')->insertOrIgnore([
                'kind' => $kind,
                'next_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        PaymentMethod::firstOrCreate(['name' => 'Cash'], ['kind' => 'cash']);
        PaymentMethod::firstOrCreate(['name' => 'Bank transfer'], ['kind' => 'bank']);

        $numeric = static function (
            string $code,
            string $label,
            string $section,
            bool $required = false,
            string $unit = 'inches'
        ): array {
            return [
                'code' => $code,
                'label' => $label,
                'section' => $section,
                'sequence' => 0,
                'type' => 'numeric',
                'unit' => $unit,
                'required' => $required,
                'min' => '0.1',
                'max' => '120',
                'help' => '',
                'options' => [],
            ];
        };

        $text = static function (string $code, string $label, string $section, bool $required = false): array {
            return [
                'code' => $code,
                'label' => $label,
                'section' => $section,
                'sequence' => 0,
                'type' => 'text',
                'unit' => 'none',
                'required' => $required,
                'min' => null,
                'max' => null,
                'help' => '',
                'options' => [],
            ];
        };

        $yesNo = static function (string $code, string $label, string $section): array {
            return [
                'code' => $code,
                'label' => $label,
                'section' => $section,
                'sequence' => 0,
                'type' => 'choice',
                'unit' => 'none',
                'required' => false,
                'min' => null,
                'max' => null,
                'help' => '',
                'options' => [
                    ['code' => 'no', 'label' => 'نہیں (No)'],
                    ['code' => 'yes', 'label' => 'ہاں (Yes)'],
                ],
                'checkbox' => true,
            ];
        };

        $kameez = 'قمیض (Kameez)';
        $shalwar = 'شلوار (Shalwar)';
        $kurta = 'کرتا (Kurta)';
        $pant = 'پینٹ / پتلون (Pant / Trouser)';
        $shirt = 'شرٹ (Shirt)';
        $coat = 'کوٹ (Coat)';
        $waistcoat = 'واسکٹ (Waistcoat)';
        $sherwani = 'شیروانی (Sherwani)';
        $style = 'سلائی / اسٹائل (Stitching / Style)';
        $notes = 'نوٹس (Notes)';

        $sets = [
            'shalwar_kameez' => [
                'name' => 'شلوار قمیض (Shalwar Kameez)',
                'fields' => [
                    $numeric('shirt_length', 'لمبائی (Length)', $kameez, true),
                    $numeric('shirt_sleeve_length', 'بازو / آستین کی لمبائی (Sleeve Length)', $kameez, true),
                    $numeric('shirt_teera', 'تیرا (Teera)', $kameez, true),
                    $numeric('shirt_chest', 'چھاتی (Chest)', $kameez, true),
                    $numeric('shirt_waist', 'کمر (Waist)', $kameez),
                    $numeric('shirt_hip', 'ہِپ (Hip)', $kameez),
                    $numeric('shirt_ghera', 'گھیرا (Ghera)', $kameez, true),
                    $numeric('shirt_collar', 'گلا / کالر (Neck / Collar)', $kameez, true),
                    $numeric('shirt_shoulder', 'کندھا (Shoulder)', $kameez),
                    $numeric('shirt_armhole', 'مونڈھا / آرم ہول (Armhole)', $kameez),
                    $numeric('shirt_arm', 'بازو کی چوڑائی (Arm / Bicep)', $kameez),
                    $numeric('shirt_sleeve_fitting', 'بازو فٹنگ (Sleeve Fitting)', $kameez),
                    $numeric('shirt_cuff_length', 'کف لمبائی (Cuff Length)', $kameez),
                    $numeric('shirt_cuff_width', 'کف چوڑائی (Cuff Width)', $kameez),
                    $numeric('shirt_half_back', 'ہاف بیک (Half Back)', $kameez),

                    $numeric('shalwar_length', 'شلوار لمبائی (Shalwar Length)', $shalwar, true),
                    $numeric('shalwar_waist', 'شلوار کمر (Shalwar Waist)', $shalwar, true),
                    $numeric('shalwar_seat_hip', 'نشست / ہِپ (Seat / Hip)', $shalwar, true),
                    $numeric('shalwar_thigh', 'ران (Thigh)', $shalwar, true),
                    $numeric('shalwar_knee', 'گھٹنا (Knee)', $shalwar),
                    $numeric('shalwar_bottom', 'پائنچہ (Pancha / Bottom)', $shalwar, true),
                    $numeric('shalwar_ghera', 'شلوار گھیرا (Shalwar Ghera)', $shalwar),
                    $numeric('shalwar_teera', 'شلوار تیرا (Shalwar Teera)', $shalwar),

                    $yesNo('front_pocket', 'فرنٹ پاکٹ (Front Pocket)', $style),
                    $yesNo('side_pocket', 'سائیڈ پاکٹ (Side Pocket)', $style),
                    $yesNo('shalwar_pocket', 'شلوار پاکٹ (Shalwar Pocket)', $style),
                    $yesNo('zip', 'زِپ (Zip)', $style),
                    $yesNo('cuff_pleats', 'کف پلیٹس (Cuff Pleats)', $style),
                    $yesNo('double_stitch', 'ڈبل سلائی (Double Stitch)', $style),
                    $text('shirt_notes', 'قمیض کی خاص ہدایات (Kameez Notes)', $notes),
                    $text('shalwar_notes', 'شلوار کی خاص ہدایات (Shalwar Notes)', $notes),
                ],
            ],

            'shalwar' => [
                'name' => 'شلوار (Shalwar)',
                'fields' => [
                    $numeric('shalwar_length', 'لمبائی (Length)', $shalwar, true),
                    $numeric('shalwar_waist', 'کمر (Waist)', $shalwar, true),
                    $numeric('shalwar_seat_hip', 'نشست / ہِپ (Seat / Hip)', $shalwar, true),
                    $numeric('shalwar_thigh', 'ران (Thigh)', $shalwar, true),
                    $numeric('shalwar_knee', 'گھٹنا (Knee)', $shalwar),
                    $numeric('shalwar_bottom', 'پائنچہ (Pancha / Bottom)', $shalwar, true),
                    $numeric('shalwar_ghera', 'شلوار گھیرا (Shalwar Ghera)', $shalwar),
                    $numeric('shalwar_teera', 'شلوار تیرا (Shalwar Teera)', $shalwar),
                    $yesNo('shalwar_pocket', 'شلوار پاکٹ (Shalwar Pocket)', $style),
                    $yesNo('zip', 'زِپ (Zip)', $style),
                    $text('shalwar_notes', 'خاص ہدایات (Notes)', $notes),
                ],
            ],

            'kurta' => [
                'name' => 'کرتا (Kurta)',
                'fields' => [
                    $numeric('kurta_length', 'لمبائی (Length)', $kurta, true),
                    $numeric('kurta_sleeve', 'بازو / آستین کی لمبائی (Sleeve Length)', $kurta, true),
                    $numeric('kurta_teera', 'تیرا (Teera)', $kurta, true),
                    $numeric('kurta_chest', 'چھاتی (Chest)', $kurta, true),
                    $numeric('kurta_waist', 'کمر (Waist)', $kurta),
                    $numeric('kurta_hip', 'ہِپ (Hip)', $kurta),
                    $numeric('kurta_ghera', 'گھیرا (Ghera)', $kurta, true),
                    $numeric('kurta_shoulder', 'کندھا (Shoulder)', $kurta),
                    $numeric('kurta_armhole', 'مونڈھا / آرم ہول (Armhole)', $kurta),
                    $numeric('kurta_arm', 'بازو کی چوڑائی (Arm / Bicep)', $kurta),
                    $numeric('kurta_collar', 'گلا / کالر (Neck / Collar)', $kurta, true),
                    $numeric('kurta_sleeve_fitting', 'بازو فٹنگ (Sleeve Fitting)', $kurta),
                    $numeric('kurta_cuff_length', 'کف لمبائی (Cuff Length)', $kurta),
                    $numeric('kurta_cuff_width', 'کف چوڑائی (Cuff Width)', $kurta),
                    $numeric('kurta_half_back', 'ہاف بیک (Half Back)', $kurta),
                    $yesNo('front_pocket', 'فرنٹ پاکٹ (Front Pocket)', $style),
                    $yesNo('side_pocket', 'سائیڈ پاکٹ (Side Pocket)', $style),
                    $yesNo('cuff_pleats', 'کف پلیٹس (Cuff Pleats)', $style),
                    $yesNo('double_stitch', 'ڈبل سلائی (Double Stitch)', $style),
                    $text('kurta_notes', 'خاص ہدایات (Notes)', $notes),
                ],
            ],

            'pant' => [
                'name' => 'پینٹ / پتلون (Pant / Trouser)',
                'fields' => [
                    $numeric('pant_length', 'لمبائی (Length)', $pant, true),
                    $numeric('pant_waist', 'کمر (Waist)', $pant, true),
                    $numeric('pant_hip', 'ہِپ / نشست (Hip / Seat)', $pant, true),
                    $numeric('pant_thigh', 'ران (Thigh)', $pant, true),
                    $numeric('pant_knee', 'گھٹنا (Knee)', $pant, true),
                    $numeric('pant_bottom', 'پائنچہ / باٹم (Bottom)', $pant, true),
                    $numeric('pant_inseam', 'اندرونی لمبائی (Inseam)', $pant),
                    $numeric('pant_outseam', 'بیرونی لمبائی (Outseam)', $pant),
                    $numeric('pant_rise', 'رائز / کروچ (Rise / Crotch)', $pant),
                    $yesNo('zip', 'زِپ (Zip)', $style),
                    $yesNo('front_pocket', 'فرنٹ پاکٹ (Front Pocket)', $style),
                    $yesNo('side_pocket', 'سائیڈ پاکٹ (Side Pocket)', $style),
                    $yesNo('back_pocket', 'بیک پاکٹ (Back Pocket)', $style),
                    $text('pant_notes', 'خاص ہدایات (Notes)', $notes),
                ],
            ],

            'shirt' => [
                'name' => 'شرٹ (Shirt)',
                'fields' => [
                    $numeric('shirt_length', 'لمبائی (Length)', $shirt, true),
                    $numeric('shirt_chest', 'چھاتی (Chest)', $shirt, true),
                    $numeric('shirt_waist', 'کمر (Waist)', $shirt),
                    $numeric('shirt_hip', 'ہِپ (Hip)', $shirt),
                    $numeric('shirt_shoulder', 'کندھا (Shoulder)', $shirt, true),
                    $numeric('shirt_sleeve', 'بازو / آستین (Sleeve)', $shirt, true),
                    $numeric('shirt_armhole', 'مونڈھا / آرم ہول (Armhole)', $shirt),
                    $numeric('shirt_arm', 'بازو کی چوڑائی (Arm / Bicep)', $shirt),
                    $numeric('shirt_collar', 'گلا / کالر (Neck / Collar)', $shirt, true),
                    $numeric('shirt_cuff_length', 'کف لمبائی (Cuff Length)', $shirt),
                    $numeric('shirt_cuff_width', 'کف چوڑائی (Cuff Width)', $shirt),
                    $numeric('shirt_half_back', 'ہاف بیک (Half Back)', $shirt),
                    $yesNo('front_pocket', 'فرنٹ پاکٹ (Front Pocket)', $style),
                    $yesNo('cuff_pleats', 'کف پلیٹس (Cuff Pleats)', $style),
                    $text('shirt_notes', 'خاص ہدایات (Notes)', $notes),
                ],
            ],

            'coat' => [
                'name' => 'کوٹ (Coat)',
                'fields' => [
                    $numeric('coat_length', 'کوٹ لمبائی (Coat Length)', $coat, true),
                    $numeric('coat_chest', 'چھاتی (Chest)', $coat, true),
                    $numeric('coat_waist', 'کمر (Waist)', $coat, true),
                    $numeric('coat_hip', 'ہِپ (Hip)', $coat),
                    $numeric('coat_shoulder', 'کندھا (Shoulder)', $coat, true),
                    $numeric('coat_sleeve', 'بازو / آستین (Sleeve)', $coat, true),
                    $numeric('coat_armhole', 'مونڈھا / آرم ہول (Armhole)', $coat),
                    $numeric('coat_arm', 'بازو کی چوڑائی (Arm / Bicep)', $coat),
                    $numeric('coat_collar', 'گلا / کالر (Neck / Collar)', $coat),
                    $numeric('coat_front', 'فرنٹ (Front)', $coat),
                    $numeric('coat_back', 'بیک / ہاف بیک (Back / Half Back)', $coat),
                    $numeric('coat_cuff', 'کف (Cuff)', $coat),
                    $yesNo('front_pocket', 'فرنٹ پاکٹ (Front Pocket)', $style),
                    $yesNo('side_pocket', 'سائیڈ پاکٹ (Side Pocket)', $style),
                    $text('coat_notes', 'خاص ہدایات (Notes)', $notes),
                ],
            ],

            'waistcoat' => [
                'name' => 'واسکٹ (Waistcoat)',
                'fields' => [
                    $numeric('waistcoat_length', 'واسکٹ لمبائی (Waistcoat Length)', $waistcoat, true),
                    $numeric('waistcoat_chest', 'چھاتی (Chest)', $waistcoat, true),
                    $numeric('waistcoat_waist', 'کمر (Waist)', $waistcoat, true),
                    $numeric('waistcoat_shoulder', 'کندھا (Shoulder)', $waistcoat, true),
                    $numeric('waistcoat_neck', 'گلا (Neck)', $waistcoat),
                    $numeric('waistcoat_front', 'فرنٹ (Front)', $waistcoat),
                    $numeric('waistcoat_back', 'بیک / ہاف بیک (Back / Half Back)', $waistcoat),
                    $yesNo('front_pocket', 'فرنٹ پاکٹ (Front Pocket)', $style),
                    $yesNo('side_pocket', 'سائیڈ پاکٹ (Side Pocket)', $style),
                    $text('waistcoat_notes', 'خاص ہدایات (Notes)', $notes),
                ],
            ],

            'sherwani' => [
                'name' => 'شیروانی (Sherwani)',
                'fields' => [
                    $numeric('sherwani_length', 'لمبائی (Length)', $sherwani, true),
                    $numeric('sherwani_chest', 'چھاتی (Chest)', $sherwani, true),
                    $numeric('sherwani_waist', 'کمر (Waist)', $sherwani, true),
                    $numeric('sherwani_hip', 'ہِپ (Hip)', $sherwani),
                    $numeric('sherwani_ghera', 'گھیرا (Ghera)', $sherwani),
                    $numeric('sherwani_teera', 'تیرا (Teera)', $sherwani, true),
                    $numeric('sherwani_shoulder', 'کندھا (Shoulder)', $sherwani),
                    $numeric('sherwani_sleeve', 'بازو / آستین (Sleeve)', $sherwani, true),
                    $numeric('sherwani_armhole', 'مونڈھا / آرم ہول (Armhole)', $sherwani),
                    $numeric('sherwani_arm', 'بازو کی چوڑائی (Arm / Bicep)', $sherwani),
                    $numeric('sherwani_collar', 'گلا / کالر (Neck / Collar)', $sherwani, true),
                    $numeric('sherwani_front', 'فرنٹ (Front)', $sherwani),
                    $numeric('sherwani_back', 'بیک / ہاف بیک (Back / Half Back)', $sherwani),
                    $numeric('sherwani_cuff', 'کف (Cuff)', $sherwani),
                    $yesNo('front_pocket', 'فرنٹ پاکٹ (Front Pocket)', $style),
                    $yesNo('side_pocket', 'سائیڈ پاکٹ (Side Pocket)', $style),
                    $text('sherwani_notes', 'خاص ہدایات (Notes)', $notes),
                ],
            ],

            'custom' => [
                'name' => 'کسٹم (Custom)',
                'fields' => [
                    $numeric('custom_length', 'لمبائی (Length)', 'کسٹم (Custom)'),
                    $numeric('custom_chest', 'چھاتی (Chest)', 'کسٹم (Custom)'),
                    $numeric('custom_waist', 'کمر (Waist)', 'کسٹم (Custom)'),
                    $numeric('custom_hip', 'ہِپ (Hip)', 'کسٹم (Custom)'),
                    $numeric('custom_shoulder', 'کندھا (Shoulder)', 'کسٹم (Custom)'),
                    $numeric('custom_sleeve', 'بازو / آستین (Sleeve)', 'کسٹم (Custom)'),
                    $numeric('custom_neck', 'گلا (Neck)', 'کسٹم (Custom)'),
                    $text('custom_notes', 'خاص ہدایات (Notes)', $notes, true),
                ],
            ],
        ];

        foreach ($sets as $code => $definition) {
            $fields = array_map(
                static function (array $field, int $index): array {
                    $field['sequence'] = $index;

                    return $field;
                },
                $definition['fields'],
                array_keys($definition['fields'])
            );

            // Starter templates are only created when missing. Re-running the
            // seeder never overwrites an owner's existing template edits.
            MeasurementTemplate::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $definition['name'],
                    'fields' => $fields,
                    'unit' => 'inches',
                    'notes' => 'Urdu + English tailoring measurement template.',
                ]
            );
        }
    }
}
