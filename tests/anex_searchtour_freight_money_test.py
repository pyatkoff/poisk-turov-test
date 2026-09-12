from pathlib import Path
import unittest

ROOT=Path(__file__).resolve().parents[1]
SCRIPT=ROOT/'scripts'/'diagnostics'/'anex_searchtour_freight_money.py'
TEXT=SCRIPT.read_text()

class SearchTourFreightMoneyTest(unittest.TestCase):
    def test_static_boundaries(self):
        self.assertIn('anex_searchtour_freight_money_20260913_v1',TEXT)
        self.assertIn('freight_money_schemas',TEXT)
        self.assertIn('surcharge|price|cost|fee|fuel|rate|currency|converted|amount|total|supplement',TEXT)
        self.assertIn("'additional_prices_requests','andromeda_requests','freight_monitor_requests','booking_calls','broninit_calls','mapping_writes'",TEXT)
        for forbidden in ('AdditionalPricesDaily','FreightMonitor_FREIGHTSBYPACKET','->bron(','bron_ticket','broninit(','->calc('):
            self.assertNotIn(forbidden,TEXT)
    def test_sensitive_values_not_projected(self):
        self.assertIn('claim|token|oauth|url|href|link|hotel|name|alias|^id$',TEXT)
        self.assertIn("'supplier_replay_allowed':False",TEXT)
        self.assertIn("forbidden in ('catclaim','oauth_token','anex_api_token','https://parser.anextour.ru')",TEXT)

if __name__=='__main__':unittest.main()
