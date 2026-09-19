import datetime as dt
import importlib.util
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location('fuel', ROOT / 'scripts/diagnostics/anex_fuel_quote_probe.py')
fuel = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(fuel)


class Transport:
    def __init__(self, error=None):
        self.calls = []
        self.error = error or {'error': 42, 'message': 'Unknown method'}
        self.day = dt.date.today() + dt.timedelta(days=14)

    def request(self, params, token):
        self.calls.append((dict(params), token))
        action = params['action']
        payloads = {
            'SearchTour_TOWNFROMS': [{'id': 2, 'name': 'Москва'}],
            'SearchTour_STATES': [{'id': 6, 'name': 'Египет'}],
            'SearchTour_CHECKIN': {},
            'SearchTour_CURRENCIES': [{'id': 1, 'name': 'RUB'}],
            'SearchTour_NIGHTS': [7],
            'SearchTour_PRICES': {'prices': [{'id': 'TEST-OPAQUE-OFFER', 'hotelKey': 5844,
                'hotel': 'TEST HOTEL', 'room': 'STANDARD', 'meal': 'AI', 'grouped': 0,
                'packetType': 0, 'adult': 2, 'child': 0, 'infant': 0,
                'checkIn': self.day.strftime('%Y%m%d'), 'checkOut': (self.day + dt.timedelta(days=7)).strftime('%Y%m%d'),
                'nights': 7, 'price': '1222.50', 'currency': 'USD', 'convertedPrice': '112727 RUB'}]},
            'Booking_CalcClaim': self.error}
        return {'status': 'ok', 'http_status': 200}, json.dumps({action: payloads[action]}).encode()

    def choose_named(self, rows, patterns):
        return rows[0] if isinstance(rows, list) and rows else None

    def positive_id(self, row):
        return int(row['id'])

    def available_dates(self, data, today):
        return [(self.day, None)]


class ProbeTest(unittest.TestCase):
    def test_price_not_promoted_to_quote(self):
        api = Transport()
        result = fuel.run_remote('TEST-TOKEN-PRIVATE', api)
        self.assertEqual(result['status'], 'search_observed_quote_not_calculated')
        self.assertFalse(result['final_price_verified'])
        self.assertEqual(result['fuel_inclusion'], 'unknown')
        self.assertEqual(result['sample']['price'], {'amount': '1222.50', 'currency': 'USD'})
        self.assertFalse(result['booking_created'])
        self.assertFalse(result['claim_initialized'])
        self.assertEqual(len(api.calls), 7)
        calc = api.calls[0][0]
        self.assertEqual(calc, {'samo_action': 'api', 'version': '1.0', 'type': 'json', 'action': 'Booking_CalcClaim'})
        self.assertNotIn('TEST-OPAQUE-OFFER', json.dumps(result))
        self.assertNotIn('TEST-TOKEN-PRIVATE', json.dumps(result))
        self.assertEqual(result['checks'][0]['error_class'], 'unknown_method')

    def test_secret_echo_is_not_logged(self):
        result = fuel.run_remote('TEST-SECRET', Transport({'error': 1000204, 'message': 'TEST-SECRET required'}))
        self.assertNotIn('TEST-SECRET', json.dumps(result))
        self.assertNotIn('error_class', result['checks'][0])

    def test_missing_parameter_is_not_access_denial(self):
        result = fuel.run_remote('TEST-SECRET', Transport({'error': 1000204, 'message': 'claim_guid is required'}))
        self.assertEqual(result['checks'][0]['error_class'], 'parameter_required')
        self.assertFalse(result['final_price_verified'])

    def test_access_denied_is_classified(self):
        result = fuel.run_remote('TEST-SECRET', Transport({'error': 7, 'message': 'Access denied'}))
        self.assertEqual(result['checks'][0]['error_class'], 'access_denied')

    def test_invalid_token_makes_no_requests(self):
        for value in ('', 'a\nb', None):
            api = Transport()
            fuel.run_remote(value, api)
            self.assertEqual(api.calls, [])

    def test_no_booking_or_payment_method(self):
        self.assertEqual({a for a in fuel.ALLOWED if a.startswith('Booking_')}, {'Booking_CalcClaim'})
        self.assertFalse(any('Save' in a or 'Bron' in a or 'Payment' in a or 'Init' in a for a in fuel.ALLOWED))

    def test_zero_and_decimal_precision(self):
        self.assertEqual(fuel.money('0'), '0')
        self.assertEqual(fuel.money('100.10'), '100.10')
        for val in (True, float('inf'), '-1', '1e5', '1.001'):
            self.assertIsNone(fuel.money(val))

    def test_encoded_secret_is_rejected(self):
        self.assertIsNone(fuel.clean_text('x%53ECRET', 'SECRET'))
        self.assertIsNone(fuel.clean_text('<script>', 'SECRET'))
        self.assertIsNone(fuel.clean_text('https://example.test', 'SECRET'))


if __name__ == '__main__':
    unittest.main()
