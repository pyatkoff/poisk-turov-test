'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');

// Render a private copy of the compiled payload using the same route rewrites as
// the NEXT publisher. This process starts no additional HTTP service.
module.exports = function prepareNextHome(base) {
  const sitePrefix = new URL(base).pathname;
  assert.equal(sitePrefix, '/_preview/search3-site-candidate');
  const nextPrefix = '/_preview/search3-next-candidate';
  const runner = fs.realpathSync(process.env.RUNNER_TEMP);
  const build = path.join(runner, 'search3-site-docroot', sitePrefix.slice(1));
  const temporary = fs.realpathSync(fs.mkdtempSync(path.join(runner, 'search3-next-home-')));
  assert.equal(path.dirname(temporary), runner);
  const payload = path.join(temporary, 'payload'), documentRoot = path.join(temporary, 'empty-document-root');
  const clean = () => fs.rmSync(temporary, {recursive:true,force:true});
  try {
    fs.cpSync(build, payload, {recursive:true});
    fs.mkdirSync(documentRoot);
    const rewrite = (name, from, to, count) => {
      const file = path.join(payload, name), input = fs.readFileSync(file, 'utf8');
      assert.equal(input.split(from).length - 1, count, 'NEXT route source ' + name);
      fs.writeFileSync(file, input.replaceAll(from, to));
    };
    rewrite('site-path-v1.php', '#^(' + sitePrefix + ')(?:/|$)#', '#^(' + nextPrefix + ')(?:/|$)#', 1);
    for (const [name, count] of Object.entries({
      'poisk-turov/index.php':3, 'visual-search/public-entry-v1.php':3,
      'visual-search/entry-v1.php':2, 'visual-search/public-config-v1.js':2,
    })) rewrite(name, sitePrefix, nextPrefix, count);
    const php = "$_SERVER['DOCUMENT_ROOT']=$argv[1]; $_SERVER['HTTP_HOST']='anytoour.ru'; $_SERVER['SCRIPT_NAME']=$argv[2]; include $argv[3];";
    const html = new Map();
    for (const entry of ['index.php','poisk-turov/index.php']) {
      const pathname = nextPrefix + '/' + entry.replace(/index\.php$/, '');
      const rendered = execFileSync('php', ['-d','display_errors=1','-r',php,
        documentRoot,nextPrefix+'/'+entry,path.join(payload,entry)], {encoding:'utf8',timeout:20000});
      assert(rendered.includes('id="search-form"') && !rendered.includes(sitePrefix), pathname);
      html.set(pathname, rendered);
    }
    return {prefix:nextPrefix,payload,html,clean};
  } catch (error) { clean(); throw error; }
};
