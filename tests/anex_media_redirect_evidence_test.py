import importlib.util
import json
import hashlib
import pathlib
import tempfile
import unittest
from unittest.mock import patch

FILE = pathlib.Path(__file__).parents[1] / 'scripts/diagnostics/anex_media_redirect_evidence.py'
spec = importlib.util.spec_from_file_location('media_evidence', FILE)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)

class EvidenceTests(unittest.TestCase):
    def test_observed_tokens_not_hotel_keys(self):
        a=m.describe_url('https://files.anextour.ru/hotel/x/o495437?hotelCode=4158')
        b=m.describe_url('https://files.anextour.ru/hotel/x/o495438?hotelCode=4158')
        self.assertNotEqual(a['media_object_token'],b['media_object_token'])
        self.assertEqual(a['hotel_code_values'],b['hotel_code_values'])
        self.assertIsNone(a['samo_hotel_id']);self.assertIsNone(a['andromeda_hotel_id'])
        self.assertFalse(a['identity_confirmed'])

    def test_all_media_formats_retained(self):
        text='<img src="//photo.samo.ru/5.4158.12345.jpg"><img src="/image/4158.jpg"><script>"https:\\/\\/other.invalid\\/photo\\/2000037570.jpg"</script>'
        urls=m.embedded_urls(text,'https://www.anextour.ru/tours/egypt/example')
        self.assertIn('https://photo.samo.ru/5.4158.12345.jpg',urls)
        self.assertIn('https://www.anextour.ru/image/4158.jpg',urls)
        self.assertIn('https://other.invalid/photo/2000037570.jpg',urls)

    def test_only_public_allowlisted_https(self):
        for u in ['http://files.anextour.ru/o1','https://files.anextour.ru.evil.test/o1','https://user:password@files.anextour.ru/o1','https://127.0.0.1/x','https://files.anextour.ru:8443/x','https://files.anextour.ru/api/search','https://samo.anextour.ru/login']:
            self.assertFalse(m.allowed(u),u)
        self.assertTrue(m.allowed(m.seeds()[0]['url']))

    def test_seed_scope(self):
        self.assertEqual(len(m.seeds()),12)
        self.assertEqual({r['anex_id'] for r in m.seeds()},{4158,1767,1768,5215})
        self.assertEqual(sum(r['kind']=='media' for r in m.seeds()),8)

    def test_no_automatic_redirect(self):
        self.assertIsNone(m.NoRedirect().redirect_request(None,None,302,'',{},'https://example.com'))

    def test_reservation_required_before_network(self):
        with tempfile.TemporaryDirectory() as tmp, patch.object(m,'build_opener') as mock:
            with self.assertRaises(FileNotFoundError):m.run(tmp)
            mock.assert_not_called()

    def test_wrong_reservation_rejected(self):
        with tempfile.TemporaryDirectory() as tmp, patch.object(m,'build_opener') as mock:
            (pathlib.Path(tmp)/'reservation.json').write_text(json.dumps({'operation_id':'old-operation'}))
            with self.assertRaises(AssertionError):m.run(tmp)
            mock.assert_not_called()

    def test_403_host_stop_and_receipt(self):
        class Response:
            code=403
            headers={}
            def __enter__(self):return self
            def __exit__(self,*args):pass
        class Opener:
            def __init__(self):self.calls=0
            def open(self,*args,**kwargs):self.calls+=1;return Response()
        opener=Opener()
        with tempfile.TemporaryDirectory() as tmp:
            p=pathlib.Path(tmp)
            (p/'reservation.json').write_text(json.dumps({'operation_id':m.OPERATION,'state':'reserved_before_public_access','seed_sha256':hashlib.sha256(json.dumps(m.seeds(),sort_keys=True).encode()).hexdigest()}))
            with patch.object(m,'build_opener',return_value=opener),patch.object(m.time,'sleep'),patch('builtins.print'),patch.dict(m.os.environ,{'GITHUB_RUN_ATTEMPT':'1'}):m.run(p)
            result=json.loads((p/'result.json').read_text());receipt=json.loads((p/'receipt.json').read_text())
            self.assertEqual(opener.calls,2) # one files host and one www host; no retry
            self.assertEqual(len(result['rows']),12)
            self.assertEqual(result['confirmed_samo_ids'],[])
            self.assertEqual(receipt['result_sha256'],hashlib.sha256((p/'result.json').read_bytes()).hexdigest())

if __name__=='__main__':unittest.main()
