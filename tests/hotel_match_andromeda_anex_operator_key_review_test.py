import sys, unittest
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts' / 'diagnostics'))
from hotel_match_andromeda_anex_operator_key_review import review

class ReviewTests(unittest.TestCase):
    def test_direct_bridge_and_image_crosscheck(self):
        doc={'payload':{'PRICES':[{'operator':'Anex Tour','operatorKey':5,'hotel':'Swiss Heaven','hotelKey':3414,'isOperatorHotelKey':0,'hotelUrl':'https://agent.anextour.ru/hotels/egypt/swiss','hotelImage':'https://gateway.samo.ru/web/data/hotel/200x200/5.5844.3414.jpg','original':{'hotelKey':5844}}]}}
        r=review(doc)
        self.assertEqual(r['counts']['direct_bridge_rows'],1)
        self.assertEqual(r['counts']['image_operator_key_matches'],1)
        self.assertEqual(r['counts']['image_andromeda_key_matches'],1)
        self.assertEqual(r['rows'][0]['anex_operator_hotel_key'],5844)
    def test_operator_scoped_is_quarantined(self):
        doc={'payload':{'PRICES':[{'operator':'Anex Tour','operatorKey':5,'hotel':'Roulette','hotelKey':'817','isOperatorHotelKey':1,'hotelUrl':'x','hotelImage':'','original':{'hotelKey':817}}]}}
        r=review(doc)
        self.assertEqual(r['counts']['direct_bridge_rows'],0)
        self.assertEqual(r['counts']['operator_key_rows_quarantined'],1)
        self.assertEqual(r['rows'][0]['status'],'quarantine_operator_scoped')
    def test_other_operator_excluded(self):
        doc={'payload':{'PRICES':[{'operator':'Intourist','operatorKey':7,'hotelKey':1,'original':{'hotelKey':2}}]}}
        self.assertEqual(review(doc)['counts']['anex_rows'],0)
if __name__=='__main__': unittest.main()
