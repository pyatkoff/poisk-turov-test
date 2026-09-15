import collections,difflib,itertools,pathlib,sys,unittest
import numpy as np
sys.path.insert(0,str(pathlib.Path(__file__).resolve().parents[1]/'scripts/diagnostics'))
from hotel_match_post50_countrywide_names import upper_bounds,forms

class RankingCertificateTest(unittest.TestCase):
 def test_upper_bound_never_prunes_a_possible_winner(self):
  strings=[''.join(x) for n in range(1,5) for x in itertools.product('abc',repeat=n)]
  matrix=np.array([[s.count(c) for c in 'abc'] for s in strings],dtype=np.int16)
  lengths=np.array(list(map(len,strings)))
  for source in strings:
   upper=upper_bounds(matrix,np.array([source.count(c) for c in 'abc']),lengths,len(source))
   for target,bound in zip(strings,upper):
    actual=difflib.SequenceMatcher(None,source,target,autojunk=False).ratio()
    self.assertLessEqual(actual,bound+1e-12)
    if bound<.8:self.assertLess(actual,.8)
 def test_zero_shared_exact_words_still_retrievable(self):
  a,b='thirangama beech','thiranagama beach'
  self.assertFalse(set(a.split())&set(b.split()))
  self.assertGreater(difflib.SequenceMatcher(None,a,b,autojunk=False).ratio(),.9)
 def test_meaningful_names_and_numbers_retained(self):
  self.assertNotEqual(forms('Royal Beach 12'),forms('Royal Garden 12'))
  self.assertNotEqual(forms('Royal Beach 12'),forms('Royal Beach 1 2'))
  self.assertIn('old distinct beach',forms('New Distinct Beach (EX. Old Distinct Beach)'))
  self.assertEqual(forms('Hotel Resort SPA'),[])

if __name__=='__main__':unittest.main()
