'use strict';
// GET-only canonical release acceptance. No publisher acquisition/renewal/closure.
const R = require('./release_priority');
const runtime = 'bd8e248f7138ea75cb9fda2dcf44beb631c633a5';
const token = process.env.GITHUB_TOKEN;
if (!token) throw Error('github_token_missing');
const getOnly = (url, opts = {}) => {
  if (opts.method !== undefined && opts.method !== 'GET') throw Error('read_only_required');
  if (opts.body !== undefined) throw Error('read_only_body');
  return fetch(url, opts);
};
const context = {repository: 'pyatkoff/max-search-bot', baseSha: runtime, now: Date.now()};
R.verifyPreviousRelease(context, token, getOnly).then(() => {
  console.log(JSON.stringify({schema:'price4294-canonical-release-v1',repository:context.repository,
    repository_sha:runtime,deploy_run_id:44329290180,technical_release_accepted:true,
    technical_publisher_free:true,verifier:'verifyPreviousRelease',publisher_override:false}));
}).catch(e => {console.error(e.message);process.exitCode=1;});
