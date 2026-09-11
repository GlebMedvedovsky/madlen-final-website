import { readFileSync, writeFileSync, mkdirSync, existsSync, readdirSync } from 'node:fs';
import { resolve, dirname, relative } from 'node:path';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
const repo=resolve(import.meta.dirname,'../..');
const reference='88e1af1b55e56cdf47085bbba11d3c130831a1bc';
const sha=bytes=>createHash('sha256').update(bytes).digest('hex');
const git=(...args)=>execFileSync('git',args,{cwd:repo,stdio:['ignore','pipe','pipe']});
const list=JSON.parse(readFileSync(resolve(repo,'scripts/release/backend-files.json')));
const stamp=new Date().toISOString().replace(/[-:]/g,'').replace(/\.\d+Z$/,'Z');
const name='madlen-release-kit-'+stamp;
const out=resolve(repo,'installation-artifacts',name);
if(existsSync(out)) throw Error('Unique output already exists');
mkdirSync(out,{recursive:true});
const put=(name,bytes)=>{const path=resolve(out,name);mkdirSync(dirname(path),{recursive:true});writeFileSync(path,bytes);};
const files={};
for(const file of list) {
  const bytes=Buffer.from(readFileSync(resolve(repo,'backend',file),'utf8').replaceAll('\r\n','\n'));
  let before=null; try {before=sha(git('show',reference+':backend/'+file));}catch{}
  if(before===sha(bytes)) throw Error('Unchanged file in overlay: '+file);
  files[file]={before,after:sha(bytes)}; put('payload/backend/'+file,bytes);
}
const runtime=path=>/^backend\/(app|config|routes|database\/migrations|lang)\//.test(path) || path==='backend/bootstrap/app.php';
const deltas=new Set([...git('diff','--name-only',reference).toString().trim().split('\n'),...git('ls-files','--others','--exclude-standard').toString().trim().split('\n')].filter(runtime));
for(const path of deltas) if(!list.includes(path.slice(8))) throw Error('Runtime change missing from allowlist: '+path);
put('manifest.json',JSON.stringify({schemaVersion:1,referenceRevision:reference,workingHead:git('rev-parse','HEAD').toString().trim(),
  committed:false,createdAt:new Date().toISOString(),
  note:'Candidate from working tree. Reference hashes describe Git 88e1af1, not verified installed Netcup files. Run inspect on Netcup before check/apply.',files},null,2)+'\n');
for(const [source,target] of Object.entries({
  'scripts/release/overlay.php':'overlay.php','scripts/release/preflight.php':'preflight.php',
  'scripts/release/backup-installed.php':'backup-installed.php',
  'docs/NETCUP_RELEASE_RU.md':'INSTALL_RU.md',
  'docs/RELEASE_VERIFICATION_RU.md':'VERIFICATION_RU.md',
  '.github/workflows/madlen-production-publisher.yml':'source/.github/workflows/madlen-production-publisher.yml',
  'scripts/production/extract-package.py':'source/scripts/production/extract-package.py',
  'src/components/CookieBanner.astro':'source/src/components/CookieBanner.astro',
  'src/components/ProjectDetail.astro':'source/src/components/ProjectDetail.astro',
})) {
  let text=readFileSync(resolve(repo,source),'utf8').replaceAll('\r\n','\n');
  if(target==='INSTALL_RU.md') text=text.replaceAll('madlen-release-kit-REPLACE_WITH_ACTUAL_NAME',name);
  put(target,text);
}
const sums=[];
function walk(dir){for(const entry of readdirSync(dir,{withFileTypes:true})){const path=resolve(dir,entry.name);if(entry.isDirectory())walk(path);else sums.push(sha(readFileSync(path))+'  '+relative(out,path).replaceAll('\\','/'));}}
walk(out); sums.sort();put('SHA256SUMS',sums.join('\n')+'\n');
// Local installer verification without producing a release archive before merge.
if (process.argv.includes('--unpacked')) {
  console.log(JSON.stringify({name,directory:out,backendFiles:list.length,archive:null},null,2));
  process.exit(0);
}
const archive=out+'.tar.gz';
execFileSync('tar',['-czf',archive,'-C',dirname(out),name]);
const checksum=sha(readFileSync(archive));
writeFileSync(archive+'.sha256',checksum+'  '+name+'.tar.gz\n');
writeFileSync(resolve(repo,'installation-artifacts/latest-release-kit.json'),JSON.stringify({name,archive,sha256:checksum,backendFiles:list.length},null,2)+'\n');
console.log(JSON.stringify({name,sha256:checksum,backendFiles:list.length},null,2));
