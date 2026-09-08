import copy
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest
from unittest import mock


ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location("anex_mapping_import", ROOT / "scripts/diagnostics/anex_search_mapping_import.py")
module = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(module)


def fixture(directory):
    exact = {"external_id": 10, "status": "verified_auto", "catalog_hotel_id": 100,
             "candidates": [{"id": 100}]}
    review = {"external_id": 20, "status": "review", "candidates": [{"id": 200}]}
    strong = {"external_id": 20, "status": "strong_candidate", "original_status": "review",
              "reason": "name_country_coordinates", "candidates": [{"id": 200}]}
    catalog = {"schema_version": 1, "provider": "anex_xml", "matches": [exact, review],
               "counts": {"anex_hotels": 2, "verified_auto": 1, "review": 1, "unmatched": 0,
                          "verified_unique_anytour": 1}}
    geo = {"schema_version": 2, "rows": [strong], "processed_total": 1,
           "counts": {"strong_candidate": 1, "review": 0, "unmatched": 0}}
    sources = {}
    for key, document in (("catalog_sha256", catalog), ("geo_sha256", geo)):
        raw = module.canonical(document)
        (directory / module.SOURCE_FILES[key]).write_bytes(raw)
        sources[key] = hashlib.sha256(raw).hexdigest()
    rows = [{"anex_hotel_id": 10, "catalog_hotel_id": 100, "match_class": "exact",
             "reason": "catalog_verified_auto", "source_row_digest": hashlib.sha256(module.canonical(exact)).hexdigest()},
            {"anex_hotel_id": 20, "catalog_hotel_id": 200, "match_class": "strong_candidate",
             "reason": strong["reason"], "source_row_digest": hashlib.sha256(module.canonical(strong)).hexdigest()}]
    document = {"schema_version": 1, "scope": "preview", "approval_policy": module.APPROVAL_POLICY,
                "sources": sources, "counts": {"exact": 1, "strong": 1, "total": 2, "unique_catalog_hotels": 2},
                "rows": rows}
    path = directory / "anex-search-mappings.json"
    path.write_bytes(module.canonical(document) + b"\n")
    return path, document


class MappingPayloadTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.directory = Path(self.temp.name)
        self.path, self.document = fixture(self.directory)

    def reject(self, document):
        self.path.write_bytes(module.canonical(document))
        with self.assertRaises(ValueError):
            module.load_mapping(self.path)

    def test_source_verified_payload_and_protocol(self):
        meta, rows = module.load_mapping(self.path)
        self.assertEqual(meta["mapping_digest"], hashlib.sha256(self.path.read_bytes()).hexdigest())
        self.assertEqual(meta["counts"], self.document["counts"])
        protocol = self.directory / "protocol.ndjson"
        module.write_protocol(protocol, meta, rows)
        messages = [json.loads(line) for line in protocol.read_text().splitlines()]
        self.assertEqual([item["type"] for item in messages], ["meta", "row", "row", "commit"])
        self.assertEqual(messages[-1]["rows_digest"], hashlib.sha256(
            b"".join(module.canonical(row) + b"\n" for row in rows)).hexdigest())
        self.assertEqual(messages[-1]["mapping_digest"], meta["mapping_digest"])

    def test_invalid_ids_classes_schema_and_policy(self):
        for field in ("anex_hotel_id", "catalog_hotel_id"):
            for value in (0, -1, True, "10", 1.5, 2_147_483_648, None):
                with self.subTest(field=field, value=value):
                    bad = copy.deepcopy(self.document)
                    bad["rows"][0][field] = value
                    self.reject(bad)
        for field, value in (("schema_version", 2), ("schema_version", True), ("scope", "production"),
                             ("approval_policy", "auto_accept")):
            bad = copy.deepcopy(self.document)
            bad[field] = value
            self.reject(bad)
        bad = copy.deepcopy(self.document)
        bad["rows"][0]["match_class"] = "review"
        self.reject(bad)

    def test_duplicate_ids_counts_and_hashes(self):
        mutations = [lambda data: data["rows"][1].update(anex_hotel_id=10),
                     lambda data: data["counts"].update(total=3),
                     lambda data: data["counts"].update(exact=True),
                     lambda data: data["counts"].update(unique_catalog_hotels=1),
                     lambda data: data["sources"].update(catalog_sha256="z" * 64),
                     lambda data: data["sources"].update(catalog_sha256="a" * 64),
                     lambda data: data["rows"][0].update(source_row_digest="b" * 64),
                     lambda data: data["rows"][0].update(catalog_hotel_id=999),
                     lambda data: data["rows"][0].update(reason="fabricated_reason")]
        for mutate in mutations:
            bad = copy.deepcopy(self.document)
            mutate(bad)
            self.reject(bad)

    def test_partial_approved_set_rejected_even_with_matching_counts(self):
        bad = copy.deepcopy(self.document)
        bad["rows"].pop()
        bad["counts"] = {"exact": 1, "strong": 0, "total": 1, "unique_catalog_hotels": 1}
        self.reject(bad)

    def test_source_totals_validated_even_after_rehash(self):
        source = self.directory / module.SOURCE_FILES["catalog_sha256"]
        catalog = json.loads(source.read_bytes())
        catalog["counts"]["anex_hotels"] = 999
        source.write_bytes(module.canonical(catalog))
        bad = copy.deepcopy(self.document)
        bad["sources"]["catalog_sha256"] = hashlib.sha256(source.read_bytes()).hexdigest()
        self.reject(bad)

    def test_remote_command_masks_credentials_and_requires_matching_report(self):
        environment = {"ANYTOOUR_DEPLOY_HOST": "host.example", "ANYTOOUR_DEPLOY_USER": "anytour",
                       "ANYTOOUR_DEPLOY_SSH_KEY": "PRIVATE KEY SECRET", "ANEX_API_TOKEN": "UNNEEDED TOKEN"}
        def completed(command, **kwargs):
            self.assertNotIn("PRIVATE KEY SECRET", " ".join(command))
            self.assertNotIn("UNNEEDED TOKEN", " ".join(command))
            self.assertNotIn("ANEX_API_TOKEN", kwargs["env"])
            self.assertNotIn("ANYTOOUR_DEPLOY_SSH_KEY", kwargs["env"])
            self.assertIn('cd "$HOME/www/anytoour.ru" && php -r ', command[-1])
            meta = json.loads(kwargs["stdin"].readline())
            return subprocess.CompletedProcess(command, 0, json.dumps({
                "status": "imported", "input_count": 2, "mapping_digest": meta["mapping_digest"]}).encode(), b"")
        with mock.patch.dict(os.environ, environment), mock.patch.object(module.subprocess, "run", side_effect=completed):
            self.assertEqual(module.ssh_import(self.path)["status"], "imported")
        wrong = subprocess.CompletedProcess([], 0, b'{"status":"imported","input_count":2,"mapping_digest":"wrong"}', b"")
        with mock.patch.dict(os.environ, environment), mock.patch.object(module.subprocess, "run", return_value=wrong):
            with self.assertRaises(RuntimeError):
                module.ssh_import(self.path)

    def test_writer_only_mutates_its_policy_table(self):
        source = (ROOT / "scripts/diagnostics/anex_search_mapping_writer.php").read_text()
        self.assertNotRegex(source, r"(?i)(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM|ALTER\s+TABLE|CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?)\s+(?:catalog_hotels|anex_hotel_decisions|anex_hotels|anex_hotel_auto_matches|anex_hotel_candidates|anex_sync_runs)\b")
        self.assertNotRegex(source, r"(?i)DELETE\s+FROM")


# A PDO boundary double runs the real PHP writer, including protocol parsing,
# SQL ordering, partial-write rollback, manual precedence and idempotence.
FAKE_HELPER = r'''<?php
class MappingTestStatement extends PDOStatement {
    private $db; private $sql; private $records = array();
    public function __construct($db, $sql) { $this->db=$db; $this->sql=$sql; }
    public function execute($params=null) { $this->records=$this->db->run($this->sql,$params ?? array()); return true; }
    public function fetchAll($mode=PDO::FETCH_DEFAULT,...$args) {
        return $mode === PDO::FETCH_COLUMN ? array_map(function($row) { return reset($row); },$this->records) : $this->records;
    }
    public function fetch($mode=PDO::FETCH_DEFAULT,$orientation=PDO::FETCH_ORI_NEXT,$offset=0) { return $this->records[0] ?? false; }
    public function fetchColumn($column=0) { return isset($this->records[0]) ? array_values($this->records[0])[$column] : false; }
}
class MappingTestPDO extends PDO {
    public $state; private $snapshot;
    public function __construct() { $this->state=json_decode(file_get_contents(getenv('FAKE_DB_FILE')),true); $this->state['opened']=true; }
    public function __destruct() { file_put_contents(getenv('FAKE_DB_FILE'),json_encode($this->state)); }
    public function setAttribute($attribute,$value) { return true; }
    public function exec($sql) { if (strpos($sql,'CREATE TABLE IF NOT EXISTS anex_hotel_search_mappings') !== 0) throw new Exception(); return 0; }
    public function beginTransaction() { $this->snapshot=$this->state['mappings']; return true; }
    public function commit() { $this->state['commits']++; return true; }
    public function rollBack() { $this->state['mappings']=$this->snapshot; $this->state['rollbacks']++; return true; }
    public function prepare($sql,$options=array()) { return new MappingTestStatement($this,$sql); }
    public function query($sql,...$args) { $statement=$this->prepare($sql); $statement->execute(); return $statement; }
    public function run($sql,$params) {
        if (strpos($sql,'SELECT ENGINE FROM information_schema.TABLES') === 0) return array(array('ENGINE'=>$this->state['engine'] ?? 'InnoDB'));
        if (strpos($sql,'SELECT id FROM catalog_hotels') === 0) {
            if (count($params)>250) throw new Exception();
            return array_map(function($id) { return array('id'=>$id); },array_values(array_intersect($params,$this->state['targets'])));
        }
        if (strpos($sql,'SELECT anex_hotel_id,decision_status') === 0) return array_values(array_filter($this->state['manual'],function($row) use($params) { return in_array($row['anex_hotel_id'],$params,true); }));
        if (strpos($sql,'SELECT anex_hotel_id,catalog_hotel_id,match_class') === 0) return array_values(array_filter($this->state['mappings'],function($row) use($params) { return in_array($row['anex_hotel_id'],$params,true); }));
        if (strpos($sql,'INSERT INTO anex_hotel_search_mappings') === 0) {
            if (($this->state['fail_insert_on'] ?? null) === $params[0]) throw new Exception();
            $row=array_combine(array('anex_hotel_id','catalog_hotel_id','match_class','scope','approval_policy','source_row_digest','mapping_digest'),$params);
            $row['enabled']=1; $this->state['mappings'][(string)$params[0]]=$row; $this->state['writes']++; return array();
        }
        if (strpos($sql,'UPDATE anex_hotel_search_mappings SET enabled=0') === 0) {
            $this->state['mappings'][(string)$params[0]]['enabled']=0; $this->state['writes']++; return array();
        }
        if (strpos($sql,'UPDATE anex_hotel_search_mappings SET catalog_hotel_id=') === 0) {
            $row=&$this->state['mappings'][(string)$params[4]];
            foreach(array('catalog_hotel_id','match_class','source_row_digest','mapping_digest') as $i=>$field) $row[$field]=$params[$i];
            $row['enabled']=1; $this->state['writes']++; return array();
        }
        if (strpos($sql,'SELECT COUNT(*) AS total_enabled') === 0) {
            $counts=array('total_enabled'=>0,'unique_catalog_hotels'=>0,'exact'=>0,'strong'=>0); $targets=array();
            $manualIds=array_column($this->state['manual'],'anex_hotel_id');
            foreach($this->state['mappings'] as $row) {
                if (!$row['enabled'] || $row['scope']!=='preview' || $row['approval_policy']!==$params[1]
                    || in_array($row['anex_hotel_id'],$manualIds,true) || !in_array($row['catalog_hotel_id'],$this->state['targets'],true)) continue;
                $counts['total_enabled']++; $targets[$row['catalog_hotel_id']]=true;
                $counts[$row['match_class']==='exact'?'exact':'strong']++;
            }
            $counts['unique_catalog_hotels']=count($targets); return array($counts);
        }
        if (strpos($sql,'SELECT COUNT(*) FROM anex_hotel_decisions') === 0) {
            $count=0; foreach($this->state['manual'] as $row) if ($row['decision_status']==='accepted' && in_array($row['catalog_hotel_id'],$this->state['targets'],true)) $count++;
            return array(array('count'=>$count));
        }
        throw new Exception('Unexpected SQL');
    }
}
function v2_data_db() { return new MappingTestPDO(); }
'''


@unittest.skipUnless(shutil.which("php"), "PHP CLI is required for writer execution")
class MappingWriterTest(unittest.TestCase):
    def setUp(self):
        MappingPayloadTest.setUp(self)
        self.host = self.directory / "anytoour.ru"
        (self.host / "data").mkdir(parents=True)
        (self.host / "data/db-v1.php").write_text(FAKE_HELPER)
        self.database = self.directory / "database.json"
        self.state = {"targets": [100, 200, 300], "manual": [], "mappings": {}, "writes": 0,
                      "commits": 0, "rollbacks": 0}
        self.save()
        meta, rows = module.load_mapping(self.path)
        protocol = self.directory / "protocol.ndjson"
        module.write_protocol(protocol, meta, rows)
        self.protocol = protocol.read_bytes()

    def save(self):
        self.database.write_text(json.dumps(self.state))

    def execute(self, protocol=None):
        completed = subprocess.run(["php", str(ROOT / "scripts/diagnostics/anex_search_mapping_writer.php")],
                                   input=self.protocol if protocol is None else protocol,
                                   cwd=self.host, capture_output=True,
                                   env=dict(os.environ, FAKE_DB_FILE=str(self.database)))
        self.assertEqual(completed.stderr, b"")
        self.state = json.loads(self.database.read_bytes())
        report = json.loads(completed.stdout)
        self.assertEqual(completed.returncode, 1 if report["status"] == "mapping_import_failed" else 0)
        return report

    def test_transactional_import_and_idempotence(self):
        first = self.execute()
        self.assertEqual((first["status"], first["inserted"], first["total_enabled"]), ("imported", 2, 2))
        second = self.execute()
        self.assertEqual((second["status"], second["unchanged"], second["inserted"]), ("already_imported", 2, 0))
        self.assertEqual(self.state["writes"], 2)

    def test_manual_override_any_status_preserved_and_policy_disabled(self):
        self.execute()
        self.state["manual"] = [{"anex_hotel_id": 10, "decision_status": "accepted", "catalog_hotel_id": 300},
                                {"anex_hotel_id": 20, "decision_status": "rejected", "catalog_hotel_id": None}]
        manual = copy.deepcopy(self.state["manual"])
        self.save()
        report = self.execute()
        self.assertEqual((report["skipped_manual"], report["inactivated_manual"], report["manual_conflicts"]), (2, 2, 2))
        self.assertEqual((report["total_enabled"], report["effective_mapped_count"]), (0, 1))
        self.assertEqual(self.state["manual"], manual)
        self.assertEqual(self.execute()["status"], "already_imported")

    def test_missing_target_and_partial_sql_failure_rollback(self):
        self.state["targets"].remove(200)
        self.save()
        self.assertEqual(self.execute()["status"], "mapping_import_failed")
        self.assertEqual((self.state["writes"], self.state["rollbacks"]), (0, 1))
        self.state["targets"].append(200)
        self.state["fail_insert_on"] = 20
        self.save()
        self.assertEqual(self.execute()["status"], "mapping_import_failed")
        self.assertFalse(self.state["mappings"])
        self.assertEqual((self.state["writes"], self.state["commits"], self.state["rollbacks"]), (1, 0, 2))

    def test_malformed_protocol_never_opens_database(self):
        lines = self.protocol.splitlines(keepends=True)
        row = json.loads(lines[1])
        row["row"]["catalog_hotel_id"] = 300
        corrupted = lines[0] + module.canonical(row) + b"\n" + b"".join(lines[2:])
        meta = json.loads(lines[0])
        meta["counts"]["exact"] = 2
        bad_counts = module.canonical(meta) + b"\n" + b"".join(lines[1:])
        for protocol in (b"".join(lines[:-1]), self.protocol + b'{}\n', corrupted, bad_counts, self.protocol[:-1]):
            with self.subTest(protocol=protocol[-60:]):
                self.assertEqual(self.execute(protocol)["status"], "mapping_import_failed")
                self.assertNotIn("opened", self.state)

    def test_foreign_scope_and_policy_fail_without_writes(self):
        self.execute()
        for field, value in (("scope", "production"), ("approval_policy", "other_policy")):
            original = copy.deepcopy(self.state)
            self.state["mappings"]["10"][field] = value
            before = copy.deepcopy(self.state["mappings"])
            writes = self.state["writes"]
            self.save()
            self.assertEqual(self.execute()["status"], "mapping_import_failed")
            self.assertEqual(self.state["mappings"], before)
            self.assertEqual(self.state["writes"], writes)
            self.state = original

    def test_existing_table_must_support_transactions(self):
        self.state["engine"] = "MyISAM"
        self.save()
        self.assertEqual(self.execute()["status"], "mapping_import_failed")
        self.assertEqual((self.state["writes"], self.state["commits"]), (0, 0))


if __name__ == "__main__":
    unittest.main()
