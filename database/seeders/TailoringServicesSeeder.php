<?php

namespace Database\Seeders;

use App\Models\MeasurementTemplate;
use App\Models\Product;
use Illuminate\Database\Seeder;

final class TailoringServicesSeeder extends Seeder
{
    public function run(): void
    {
        $templatesData = json_decode(<<<'JSON_DATA'
{
  "shalwar_kameez": {
    "name": "شلوار قمیض",
    "fields": [
      {
        "code": "sq_1_length",
        "label": "1. لمبائی",
        "section": "حصہ اوّل — بنیادی ناپ (1 تا 16)",
        "sequence": 0,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "sq_2_sleeve",
        "label": "2. بازو",
        "section": "حصہ اوّل — بنیادی ناپ (1 تا 16)",
        "sequence": 1,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "sq_3_teera",
        "label": "3. تیرا",
        "section": "حصہ اوّل — بنیادی ناپ (1 تا 16)",
        "sequence": 2,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "sq_4_chest",
        "label": "4. چھاتی",
        "section": "حصہ اوّل — بنیادی ناپ (1 تا 16)",
        "sequence": 3,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "sq_5_ghera",
        "label": "5. گھیرا",
        "section": "حصہ اوّل — بنیادی ناپ (1 تا 16)",
        "sequence": 4,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "sq_6_neck",
        "label": "6. گلا",
        "section": "حصہ اوّل — بنیادی ناپ (1 تا 16)",
        "sequence": 5,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "sq_7_shalwar",
        "label": "7. شلوار",
        "section": "حصہ اوّل — بنیادی ناپ (1 تا 16)",
        "sequence": 6,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "sq_8_paincha",
        "label": "8. پائنچہ",
        "section": "حصہ اوّل — بنیادی ناپ (1 تا 16)",
        "sequence": 7,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "sq_9_waist",
        "label": "9. کمر",
        "section": "حصہ اوّل — بنیادی ناپ (1 تا 16)",
        "sequence": 8,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "sq_10_hip",
        "label": "10. کولہا",
        "section": "حصہ اوّل — بنیادی ناپ (1 تا 16)",
        "sequence": 9,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "sq_11_shoulder",
        "label": "11. کندھا",
        "section": "حصہ اوّل — بنیادی ناپ (1 تا 16)",
        "sequence": 10,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "sq_12_sleeve_elbow",
        "label": "12. بازو کہنی",
        "section": "حصہ اوّل — بنیادی ناپ (1 تا 16)",
        "sequence": 11,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "sq_13_sleeve_open",
        "label": "13. بازو اوپن",
        "section": "حصہ اوّل — بنیادی ناپ (1 تا 16)",
        "sequence": 12,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "sq_14_shalwar_ghera",
        "label": "14. شلوار گھیرا",
        "section": "حصہ اوّل — بنیادی ناپ (1 تا 16)",
        "sequence": 13,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "sq_15_cuff_width",
        "label": "15. کف چوڑائی",
        "section": "حصہ اوّل — بنیادی ناپ (1 تا 16)",
        "sequence": 14,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "sq_16_cuff_length",
        "label": "16. کف لمبائی",
        "section": "حصہ اوّل — بنیادی ناپ (1 تا 16)",
        "sequence": 15,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "sq_17_front_pocket",
        "label": "17. فرنٹ پاکٹ",
        "section": "حصہ دوم — جیبیں (17 تا 19)",
        "sequence": 16,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "1",
            "label": "1 جیب"
          },
          {
            "code": "2",
            "label": "2 جیبیں"
          }
        ]
      },
      {
        "code": "sq_18_side_pocket",
        "label": "18. سائیڈ پاکٹ",
        "section": "حصہ دوم — جیبیں (17 تا 19)",
        "sequence": 17,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "1",
            "label": "1 جیب"
          },
          {
            "code": "2",
            "label": "2 جیبیں"
          }
        ]
      },
      {
        "code": "sq_19_shalwar_pocket",
        "label": "19. شلوار پاکٹ",
        "section": "حصہ دوم — جیبیں (17 تا 19)",
        "sequence": 18,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "1",
            "label": "1 جیب"
          },
          {
            "code": "2",
            "label": "2 جیبیں"
          }
        ]
      },
      {
        "code": "sq_20_shoulder_down",
        "label": "20. کندھا ڈاؤن / کندھے کا جھکاؤ",
        "section": "حصہ سوم — اضافی ناپ (20)",
        "sequence": 19,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "sq_21_design",
        "label": "21. ڈیزائن / اسٹائل",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 20,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "sq_21_design_notes",
        "label": "21. ڈیزائن / اسٹائل — نوٹس",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 21,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "sq_21_design",
        "options": []
      },
      {
        "code": "sq_22_double_stitch",
        "label": "22. ڈبل سلائی",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 22,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": "stitch_type",
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "sq_22_double_stitch_notes",
        "label": "22. ڈبل سلائی — نوٹس",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 23,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "sq_22_double_stitch",
        "options": []
      },
      {
        "code": "sq_23_single_stitch",
        "label": "23. سنگل سلائی",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 24,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": "stitch_type",
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "sq_23_single_stitch_notes",
        "label": "23. سنگل سلائی — نوٹس",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 25,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "sq_23_single_stitch",
        "options": []
      },
      {
        "code": "sq_24_cuff_pleats",
        "label": "24. کف پلیٹ",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 26,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "sq_24_cuff_pleats_notes",
        "label": "24. کف پلیٹ — نوٹس",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 27,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "sq_24_cuff_pleats",
        "options": []
      },
      {
        "code": "sq_25_ban",
        "label": "25. بین",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 28,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": "ban_collar",
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "sq_25_ban_notes",
        "label": "25. بین — نوٹس",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 29,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "sq_25_ban",
        "options": []
      },
      {
        "code": "sq_26_full_ban",
        "label": "26. فل بین",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 30,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": "ban_collar",
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "sq_26_full_ban_notes",
        "label": "26. فل بین — نوٹس",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 31,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "sq_26_full_ban",
        "options": []
      },
      {
        "code": "sq_27_half_ban",
        "label": "27. ہاف بین",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 32,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": "ban_collar",
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "sq_27_half_ban_notes",
        "label": "27. ہاف بین — نوٹس",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 33,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "sq_27_half_ban",
        "options": []
      },
      {
        "code": "sq_28_collar",
        "label": "28. کالر",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 34,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": "ban_collar",
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "sq_28_collar_notes",
        "label": "28. کالر — نوٹس",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 35,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "sq_28_collar",
        "options": []
      },
      {
        "code": "sq_29_sleeve_cuff",
        "label": "29. بازو کف",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 36,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "sq_29_sleeve_cuff_notes",
        "label": "29. بازو کف — نوٹس",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 37,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "sq_29_sleeve_cuff",
        "options": []
      },
      {
        "code": "sq_30_round_ghera",
        "label": "30. گھیرا گول",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 38,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": "ghera_shape",
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "sq_30_round_ghera_notes",
        "label": "30. گھیرا گول — نوٹس",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 39,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "sq_30_round_ghera",
        "options": []
      },
      {
        "code": "sq_31_square_ghera",
        "label": "31. گھیرا چوکور",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 40,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": "ghera_shape",
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "sq_31_square_ghera_notes",
        "label": "31. گھیرا چوکور — نوٹس",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 41,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "sq_31_square_ghera",
        "options": []
      },
      {
        "code": "sq_32_shalwar_design",
        "label": "32. شلوار ڈیزائن",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 42,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "sq_32_shalwar_design_notes",
        "label": "32. شلوار ڈیزائن — نوٹس",
        "section": "حصہ چہارم — سلائی اور ڈیزائن (21 تا 32)",
        "sequence": 43,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "sq_32_shalwar_design",
        "options": []
      }
    ],
    "unit": "inches",
    "notes": "شلوار قمیض مکمل ۳۲ نکاتی ناپ اور ڈیزائن فارم",
    "price": 150000
  },
  "waistcoat": {
    "name": "ویسٹ کوٹ",
    "fields": [
      {
        "code": "wc_1_length",
        "label": "۱۔ لمبائی",
        "section": "۱۔ بنیادی ناپ (۱ تا ۶)",
        "sequence": 0,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "wc_2_teera",
        "label": "۲۔ تیرا",
        "section": "۱۔ بنیادی ناپ (۱ تا ۶)",
        "sequence": 1,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "wc_3_chest",
        "label": "۳۔ چھاتی",
        "section": "۱۔ بنیادی ناپ (۱ تا ۶)",
        "sequence": 2,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "wc_4_neck",
        "label": "۴۔ گلا",
        "section": "۱۔ بنیادی ناپ (۱ تا ۶)",
        "sequence": 3,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "wc_5_waist",
        "label": "۵۔ کمر",
        "section": "۱۔ بنیادی ناپ (۱ تا ۶)",
        "sequence": 4,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "wc_6_hip",
        "label": "۶۔ ہپ / کولہا",
        "section": "۱۔ بنیادی ناپ (۱ تا ۶)",
        "sequence": 5,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "wc_7_ban",
        "label": "۷۔ بین",
        "section": "۲۔ اسٹائل اور ڈیزائن (۷ تا ۱۱)",
        "sequence": 6,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": "wc_neck",
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "wc_7_ban_notes",
        "label": "۷۔ بین — نوٹس",
        "section": "۲۔ اسٹائل اور ڈیزائن (۷ تا ۱۱)",
        "sequence": 7,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "wc_7_ban",
        "options": []
      },
      {
        "code": "wc_8_round_neck",
        "label": "۸۔ گول گلا",
        "section": "۲۔ اسٹائل اور ڈیزائن (۷ تا ۱۱)",
        "sequence": 8,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": "wc_neck",
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "wc_8_round_neck_notes",
        "label": "۸۔ گول گلا — نوٹس",
        "section": "۲۔ اسٹائل اور ڈیزائن (۷ تا ۱۱)",
        "sequence": 9,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "wc_8_round_neck",
        "options": []
      },
      {
        "code": "wc_9_v_neck",
        "label": "۹۔ وی گلا",
        "section": "۲۔ اسٹائل اور ڈیزائن (۷ تا ۱۱)",
        "sequence": 10,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": "wc_neck",
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "wc_9_v_neck_notes",
        "label": "۹۔ وی گلا — نوٹس",
        "section": "۲۔ اسٹائل اور ڈیزائن (۷ تا ۱۱)",
        "sequence": 11,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "wc_9_v_neck",
        "options": []
      },
      {
        "code": "wc_10_side_chaak",
        "label": "۱۰۔ سائیڈ چاک",
        "section": "۲۔ اسٹائل اور ڈیزائن (۷ تا ۱۱)",
        "sequence": 12,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "wc_10_side_chaak_notes",
        "label": "۱۰۔ سائیڈ چاک — نوٹس",
        "section": "۲۔ اسٹائل اور ڈیزائن (۷ تا ۱۱)",
        "sequence": 13,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "wc_10_side_chaak",
        "options": []
      },
      {
        "code": "wc_11_ghera",
        "label": "۱۱۔ گھیرا گول / چوکور",
        "section": "۲۔ اسٹائل اور ڈیزائن (۷ تا ۱۱)",
        "sequence": 14,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "round",
            "label": "گول"
          },
          {
            "code": "square",
            "label": "چوکور"
          }
        ]
      },
      {
        "code": "wc_11_ghera_notes",
        "label": "۱۱۔ گھیرا — نوٹس",
        "section": "۲۔ اسٹائل اور ڈیزائن (۷ تا ۱۱)",
        "sequence": 15,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "wc_11_ghera",
        "options": []
      },
      {
        "code": "wc_12_additional_notes",
        "label": "۱۲۔ اضافی نوٹس (عمومی ڈیزائن، سلائی اور فٹنگ کی اضافی ہدایات)",
        "section": "۳۔ آخری مشترکہ نوٹس (۱۲)",
        "sequence": 16,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": null,
        "options": []
      }
    ],
    "unit": "inches",
    "notes": "ویسٹ کوٹ مکمل ۱۲ نکاتی ناپ اور ڈیزائن فارم",
    "price": 120000
  },
  "pant": {
    "name": "پینٹ",
    "fields": [
      {
        "code": "pant_1_length",
        "label": "۱۔ لمبائی",
        "section": "۱۔ بنیادی ناپ (۱ تا ۶)",
        "sequence": 0,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "pant_2_waist",
        "label": "۲۔ ویسٹ",
        "section": "۱۔ بنیادی ناپ (۱ تا ۶)",
        "sequence": 1,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "pant_3_kamar",
        "label": "۳۔ کمر",
        "section": "۱۔ بنیادی ناپ (۱ تا ۶)",
        "sequence": 2,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "pant_4_hip",
        "label": "۴۔ ہپ (کولہا)",
        "section": "۱۔ بنیادی ناپ (۱ تا ۶)",
        "sequence": 3,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "pant_5_thigh",
        "label": "۵۔ ران",
        "section": "۱۔ بنیادی ناپ (۱ تا ۶)",
        "sequence": 4,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "pant_6_paincha",
        "label": "۶۔ پائنچہ",
        "section": "۱۔ بنیادی ناپ (۱ تا ۶)",
        "sequence": 5,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "pant_7_pleats",
        "label": "۷۔ پینٹ کی پلیٹیں",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 6,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "pant_7_pleats_notes",
        "label": "۷۔ پینٹ کی پلیٹیں — نوٹس",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 7,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "pant_7_pleats",
        "options": []
      },
      {
        "code": "pant_8_side_pocket",
        "label": "۸۔ سائیڈ پاکٹ",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 8,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "1",
            "label": "۱ جیب"
          },
          {
            "code": "2",
            "label": "۲ جیبیں"
          }
        ]
      },
      {
        "code": "pant_8_side_pocket_notes",
        "label": "۸۔ سائیڈ پاکٹ — نوٹس",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 9,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "pant_8_side_pocket",
        "options": []
      },
      {
        "code": "pant_9_back_pocket",
        "label": "۹۔ پچھلی پاکٹ",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 10,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "1",
            "label": "۱ جیب"
          },
          {
            "code": "2",
            "label": "۲ جیبیں"
          }
        ]
      },
      {
        "code": "pant_9_back_pocket_notes",
        "label": "۹۔ پچھلی پاکٹ — نوٹس",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 11,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "pant_9_back_pocket",
        "options": []
      },
      {
        "code": "pant_10_zip",
        "label": "۱۰۔ زپ",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 12,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "pant_10_zip_notes",
        "label": "۱۰۔ زپ — نوٹس",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 13,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "pant_10_zip",
        "options": []
      },
      {
        "code": "pant_11_belt_loop",
        "label": "۱۱۔ بیلٹ لوپ",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 14,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "pant_11_belt_loop_notes",
        "label": "۱۱۔ بیلٹ لوپ — نوٹس",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 15,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "pant_11_belt_loop",
        "options": []
      },
      {
        "code": "pant_12_elastic",
        "label": "۱۲۔ کمر میں الاسٹک",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 16,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "pant_12_elastic_notes",
        "label": "۱۲۔ کمر میں الاسٹک — نوٹس",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 17,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "pant_12_elastic",
        "options": []
      },
      {
        "code": "pant_13_paincha_fold",
        "label": "۱۳۔ پائنچے کا فولڈ",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 18,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "pant_13_paincha_fold_notes",
        "label": "۱۳۔ پائنچے کا فولڈ — نوٹس",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 19,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "pant_13_paincha_fold",
        "options": []
      },
      {
        "code": "pant_14_double_stitch",
        "label": "۱۴۔ ڈبل سلائی",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 20,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "pant_14_double_stitch_notes",
        "label": "۱۴۔ ڈبل سلائی — نوٹس",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 21,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "pant_14_double_stitch",
        "options": []
      },
      {
        "code": "pant_15_fitting_cut",
        "label": "۱۵۔ خاص فٹنگ / کٹنگ",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 22,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "pant_15_fitting_cut_notes",
        "label": "۱۵۔ خاص فٹنگ / کٹنگ — نوٹس",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 23,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "pant_15_fitting_cut",
        "options": []
      },
      {
        "code": "pant_16_extra_design",
        "label": "۱۶۔ اضافی ڈیزائن",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 24,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "pant_16_extra_design_notes",
        "label": "۱۶۔ اضافی ڈیزائن — نوٹس",
        "section": "۲۔ پینٹ کے اسٹائل اور ڈیزائن (۷ تا ۱۶)",
        "sequence": 25,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "pant_16_extra_design",
        "options": []
      },
      {
        "code": "pant_17_additional_notes",
        "label": "۱۷۔ اضافی نوٹس (کسٹمر کی خصوصی ہدایات، فٹنگ، کپڑے اور دوسری ضروری تفصیلات)",
        "section": "۳۔ آخری مشترکہ نوٹس (۱۷)",
        "sequence": 26,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": null,
        "options": []
      }
    ],
    "unit": "inches",
    "notes": "پینٹ مکمل ۱۷ نکاتی ناپ اور ڈیزائن فارم",
    "price": 100000
  },
  "coat_sherwani": {
    "name": "کوٹ / شیروانی",
    "fields": [
      {
        "code": "cs_1_length",
        "label": "۱۔ لمبائی",
        "section": "۱۔ بنیادی ناپ (۱ تا ۹)",
        "sequence": 0,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "cs_2_sleeve",
        "label": "۲۔ بازو",
        "section": "۱۔ بنیادی ناپ (۱ تا ۹)",
        "sequence": 1,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "cs_3_teera",
        "label": "۳۔ تیرا",
        "section": "۱۔ بنیادی ناپ (۱ تا ۹)",
        "sequence": 2,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "cs_4_chest",
        "label": "۴۔ چھاتی",
        "section": "۱۔ بنیادی ناپ (۱ تا ۹)",
        "sequence": 3,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "cs_5_neck",
        "label": "۵۔ گلا",
        "section": "۱۔ بنیادی ناپ (۱ تا ۹)",
        "sequence": 4,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "cs_6_waist",
        "label": "۶۔ کمر",
        "section": "۱۔ بنیادی ناپ (۱ تا ۹)",
        "sequence": 5,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "cs_7_seat",
        "label": "۷۔ سیٹ",
        "section": "۱۔ بنیادی ناپ (۱ تا ۹)",
        "sequence": 6,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "cs_8_half_back",
        "label": "۸۔ ہاف بیک / دوسرا بیک ناپ",
        "section": "۱۔ بنیادی ناپ (۱ تا ۹)",
        "sequence": 7,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "cs_9_final_back",
        "label": "۹۔ آخری بیک ناپ",
        "section": "۱۔ بنیادی ناپ (۱ تا ۹)",
        "sequence": 8,
        "type": "numeric",
        "unit": "inches",
        "min": "0.1",
        "max": "150",
        "required": false,
        "help": "",
        "options": []
      },
      {
        "code": "cs_10_collar_design",
        "label": "۱۰۔ کالر ڈیزائن",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 9,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "cs_10_collar_design_notes",
        "label": "۱۰۔ کالر ڈیزائن — نوٹس",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 10,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "cs_10_collar_design",
        "options": []
      },
      {
        "code": "cs_11_front_design",
        "label": "۱۱۔ فرنٹ ڈیزائن",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 11,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "cs_11_front_design_notes",
        "label": "۱۱۔ فرنٹ ڈیزائن — نوٹس",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 12,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "cs_11_front_design",
        "options": []
      },
      {
        "code": "cs_12_single_breast",
        "label": "۱۲۔ سنگل بریسٹ",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 13,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": "breast_type",
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "cs_12_single_breast_notes",
        "label": "۱۲۔ سنگل بریسٹ — نوٹس",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 14,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "cs_12_single_breast",
        "options": []
      },
      {
        "code": "cs_13_double_breast",
        "label": "۱۳۔ ڈبل بریسٹ",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 15,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": "breast_type",
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "cs_13_double_breast_notes",
        "label": "۱۳۔ ڈبل بریسٹ — نوٹس",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 16,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "cs_13_double_breast",
        "options": []
      },
      {
        "code": "cs_14_button_design",
        "label": "۱۴۔ بٹن ڈیزائن",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 17,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "cs_14_button_design_notes",
        "label": "۱۴۔ بٹن ڈیزائن — نوٹس",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 18,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "cs_14_button_design",
        "options": []
      },
      {
        "code": "cs_15_front_pocket",
        "label": "۱۵۔ فرنٹ پاکٹ",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 19,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "cs_15_front_pocket_notes",
        "label": "۱۵۔ فرنٹ پاکٹ — نوٹس",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 20,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "cs_15_front_pocket",
        "options": []
      },
      {
        "code": "cs_16_side_pocket",
        "label": "۱۶۔ سائیڈ پاکٹ",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 21,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "cs_16_side_pocket_notes",
        "label": "۱۶۔ سائیڈ پاکٹ — نوٹس",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 22,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "cs_16_side_pocket",
        "options": []
      },
      {
        "code": "cs_17_inner_pocket",
        "label": "۱۷۔ اندرونی پاکٹ",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 23,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "cs_17_inner_pocket_notes",
        "label": "۱۷۔ اندرونی پاکٹ — نوٹس",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 24,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "cs_17_inner_pocket",
        "options": []
      },
      {
        "code": "cs_18_sleeve_cuff",
        "label": "۱۸۔ آستین کف ڈیزائن",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 25,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "cs_18_sleeve_cuff_notes",
        "label": "۱۸۔ آستین کف ڈیزائن — نوٹس",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 26,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "cs_18_sleeve_cuff",
        "options": []
      },
      {
        "code": "cs_19_sleeve_buttons",
        "label": "۱۹۔ آستین کے بٹن",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 27,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "cs_19_sleeve_buttons_notes",
        "label": "۱۹۔ آستین کے بٹن — نوٹس",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 28,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "cs_19_sleeve_buttons",
        "options": []
      },
      {
        "code": "cs_20_shoulder_pads",
        "label": "۲۰۔ شولڈر پیڈ",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 29,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "cs_20_shoulder_pads_notes",
        "label": "۲۰۔ شولڈر پیڈ — نوٹس",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 30,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "cs_20_shoulder_pads",
        "options": []
      },
      {
        "code": "cs_21_back_chaak",
        "label": "۲۱۔ بیک چاک",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 31,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "cs_21_back_chaak_notes",
        "label": "۲۱۔ بیک چاک — نوٹس",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 32,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "cs_21_back_chaak",
        "options": []
      },
      {
        "code": "cs_22_daman_design",
        "label": "۲۲۔ دامن ڈیزائن",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 33,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "cs_22_daman_design_notes",
        "label": "۲۲۔ دامن ڈیزائن — نوٹس",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 34,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "cs_22_daman_design",
        "options": []
      },
      {
        "code": "cs_23_inner_lining",
        "label": "۲۳۔ اندرونی استر",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 35,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "cs_23_inner_lining_notes",
        "label": "۲۳۔ اندرونی استر — نوٹس",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 36,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "cs_23_inner_lining",
        "options": []
      },
      {
        "code": "cs_24_embroidery",
        "label": "۲۴۔ کڑھائی / ٹلہ ورک",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 37,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "cs_24_embroidery_notes",
        "label": "۲۴۔ کڑھائی / ٹلہ ورک — نوٹس",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 38,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "cs_24_embroidery",
        "options": []
      },
      {
        "code": "cs_25_piping_border",
        "label": "۲۵۔ پائپنگ / بارڈر ڈیزائن",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 39,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "cs_25_piping_border_notes",
        "label": "۲۵۔ پائپنگ / بارڈر ڈیزائن — نوٹس",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 40,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "cs_25_piping_border",
        "options": []
      },
      {
        "code": "cs_26_additional_design",
        "label": "۲۶۔ اضافی ڈیزائن",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 41,
        "type": "choice",
        "unit": "none",
        "required": false,
        "help": "",
        "group": null,
        "options": [
          {
            "code": "no",
            "label": "نہیں"
          },
          {
            "code": "yes",
            "label": "ہاں"
          }
        ]
      },
      {
        "code": "cs_26_additional_design_notes",
        "label": "۲۶۔ اضافی ڈیزائن — نوٹس",
        "section": "۲۔ سلائی اور ڈیزائن کے پوائنٹس (۱۰ تا ۲۶)",
        "sequence": 42,
        "type": "text",
        "unit": "none",
        "required": false,
        "help": "",
        "depends_on": "cs_26_additional_design",
        "options": []
      }
    ],
    "unit": "inches",
    "notes": "کوٹ / شیروانی مکمل ۲۶ نکاتی ناپ اور ڈیزائن فارم",
    "price": 350000
  }
}
JSON_DATA, true);

        foreach ($templatesData as $code => $data) {
            $template = MeasurementTemplate::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $data['name'],
                    'unit' => $data['unit'],
                    'fields' => $data['fields'],
                    'notes' => $data['notes'],
                    'active' => true,
                    'revision' => 1,
                ]
            );

            Product::updateOrCreate(
                ['name' => $data['name']],
                [
                    'category' => 'سلائی سروسز (Stitching)',
                    'template_id' => $template->id,
                    'price' => $data['price'],
                    'cost_known' => false,
                    'cost' => 0,
                    'taxes' => [],
                    'active' => true,
                ]
            );
        }
    }
}
