from pathlib import Path
APP=(Path(__file__).resolve().parents[1]/'v2/visual-search/app.js').read_text()
def test_explicit_live_searched_url_starts_search():
    assert "const requestedSearch=new URLSearchParams(location.search).get('searched')==='1'" in APP
    assert "state.hasSearched=requestedSearch" in APP
    assert "if(requestedSearch)runSearch()" in APP
def test_live_plain_open_does_not_unconditionally_start():
    live=APP.split("if(data.live){",1)[1].split("}else{state.hasSearched=true;runSearch();}",1)[0]
    assert "if(requestedSearch)runSearch()" in live
