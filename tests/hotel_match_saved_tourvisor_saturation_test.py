import importlib.util
import pathlib
import unittest

MODULE=pathlib.Path(__file__).resolve().parents[1]/"scripts"/"diagnostics"/"hotel_match_saved_tourvisor_saturation.py"
spec=importlib.util.spec_from_file_location("saturation",MODULE)
m=importlib.util.module_from_spec(spec); spec.loader.exec_module(m)

def doc(date, hotels):
    return {"criteria":{"date":date},"providers":{"tourvisor":{"hotels":hotels}}}

class SaturationTest(unittest.TestCase):
    def test_repeatability_duplicate_and_collision_fail_closed(self):
        residual=[
            {"anex_hotel_id":"32745","hotel_name":"Raimond Hotel","search_count":45},
            {"anex_hotel_id":"28882","hotel_name":"Malkoc Hotel","search_count":52},
            {"anex_hotel_id":"32743","hotel_name":"Malkoc Hotel","search_count":52},
            {"anex_hotel_id":"32880","hotel_name":"Grand Emin Hotel","search_count":52},
            {"anex_hotel_id":"16330","hotel_name":"Mira Beach Resort Bodrum","search_count":61},
        ]
        snaps=[
            ("a",doc("2026-10-01",[
                {"id":"70943","name":"RAIMOND HOTEL KUMKAPI","aliases":[]},
                {"id":"43528","name":"MALKOC HOTEL LALELI","aliases":[]},
                {"id":"17443","name":"GRAND EMIN HOTEL LALELI","aliases":[]},
                {"id":"9250","name":"BODRUM BEACH RESORT","aliases":[]},
            ])),
            ("b",doc("2026-10-02",[
                {"id":"70943","name":"RAIMOND HOTEL KUMKAPI","aliases":[]},
                {"id":"43528","name":"MALKOC HOTEL LALELI","aliases":[]},
                {"id":"17444","name":"GRAND EMIR","aliases":[]},
                {"id":"9250","name":"BODRUM BEACH RESORT","aliases":[]},
            ])),
        ]
        out=m.build(residual,snaps)
        rows={r["anex_hotel_id"]:r for r in out["repeatable_rows"]}
        self.assertEqual(rows["32745"]["status"],"repeatable_saved_tv_candidate_only")
        self.assertEqual(rows["28882"]["status"],"duplicate_provider_family_hold")
        self.assertEqual(rows["32743"]["status"],"duplicate_provider_family_hold")
        self.assertNotIn("16330",rows)
        self.assertEqual(out["collision_dossiers"][0]["collision_tv_id"],"17444")

    def test_same_date_artifacts_count_once(self):
        residual=[{"anex_hotel_id":"29092","hotel_name":"Adora Hotel","search_count":1}]
        hotel={"id":"17272","name":"ADORA HOTEL","aliases":[]}
        out=m.build(residual,[("a",doc("2026-10-01",[hotel])),("b",doc("2026-10-01",[hotel]))])
        self.assertEqual(out["counts"]["snapshot_unique_dates"],1)
        self.assertEqual(out["counts"]["repeatable_same_tv_id_ids"],0)
        self.assertEqual(out["single_date_rows"][0]["unique_date_support"],1)

    def test_near_spelling_requires_conservative_token_similarity(self):
        rose={"id":"59085","name":"THE ROSE BOUTIQUES SULTANAHMET","aliases":[]}
        self.assertIsNotNone(m.candidate_score("Hotel The Rose Bouquetes",rose))
        emir={"id":"17444","name":"GRAND EMIR","aliases":[]}
        self.assertIsNone(m.candidate_score("Grand Emin Hotel",emir))

if __name__=="__main__":
    unittest.main()
