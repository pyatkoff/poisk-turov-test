"use strict";
const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const {spawn}=require('node:child_process');
const {pipeline}=require('node:stream/promises');
const publicKey="-----BEGIN PUBLIC KEY-----\nMIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEAsLIV84MZuQCQX9MpIeVo\nNzo+kP8jBlICLi92xM3EeXtfp1D4OlOj+dx19R1DQ6gJ98I7jlgErEaiaR0TCjPL\nzaw8J44Rv9iZuSarthgIirCuX3gRG/YQpYtGqNGR7zGK7L35L8iSPdQqpRAn57FZ\nTRFJuLPJR+BeWhxRC/qEdeRbARWLpU3XfiD/k1IyhDastUdwXRbbjJLJaGbfmOiX\n6J0TRUIpwjdwgqeQM+wwk5/YL8e7TbjnDeJfzalJiLeh802sNQFLfACwsnpvl3Vd\nWNC3Yf2XMnlTA8gEEoHBXSBQAWnMwmCKCSaSBcv2bqePyowKWCPt11ItaKw/vifS\n52szeKcznOz33zChfuA+XD9wdsAAOXkPO1sRNGJtFKHGqZM1rpEvUzbNuCbqe+27\nbsq8koU2FEXFFj3qUU3wSq7ql/dbfVXnBZ6KGaRv97peTtpIFQ9dGTVMKzFd6npR\n5BmxTQa9Qf/OCusYQL3BOYkIRQa8+xumso+R+2OrZPlNAgMBAAE=\n-----END PUBLIC KEY-----\n";
const root=process.env.LIVE_EVIDENCE_DIR,out=process.env.LIVE_ENCRYPTED_DIR;
(async()=>{
 if(!root||!out||!path.isAbsolute(root)||!path.isAbsolute(out)||root===out)throw Error('EVIDENCE_PATHS_REQUIRED');
 fs.mkdirSync(out,{mode:0o700});
 const head=process.env.CAPTURE_TOOL_SOURCE_SHA,run=process.env.GITHUB_RUN_ID;
 const aad=Buffer.from(JSON.stringify({operation:'search3-live-capture-20261110-v2',head,run}));
 if(!fs.existsSync(root)){
  fs.writeFileSync(path.join(out,'public-status.json'),JSON.stringify({status:'LIVE_SCRIPT_NOT_INVOKED',head,run})+'\n',{flag:'wx'});
  return;
 }
 const key=crypto.randomBytes(32),iv=crypto.randomBytes(12);
 const cipher=crypto.createCipheriv('aes-256-gcm',key,iv);cipher.setAAD(aad);
 const encrypted=path.join(out,'evidence.tar.gz.aes256gcm');
 const tar=spawn('tar',['-czf','-','-C',root,'.'],{stdio:['ignore','pipe','ignore']});
 const exited=new Promise((resolve,reject)=>{tar.once('error',reject);tar.once('close',code=>code===0?resolve():reject(Error('ARCHIVE_FAILED')));});
 await Promise.all([pipeline(tar.stdout,cipher,fs.createWriteStream(encrypted,{flags:'wx',mode:0o600})),exited]);
 const envelope={format:'search3-private-evidence-v1',algorithm:'AES-256-GCM',keyWrap:'RSA-OAEP-SHA256',
  aad:aad.toString('base64'),iv:iv.toString('base64'),tag:cipher.getAuthTag().toString('base64'),
  wrappedKey:crypto.publicEncrypt({key:publicKey,oaepHash:'sha256'},key).toString('base64'),
  publicKeySha256:crypto.createHash('sha256').update(publicKey).digest('hex'),
  encryptedSha256:crypto.createHash('sha256').update(fs.readFileSync(encrypted)).digest('hex')};
 key.fill(0);
 fs.writeFileSync(path.join(out,'envelope.json'),JSON.stringify(envelope,null,2)+'\n',{flag:'wx',mode:0o600});
 const reportPath=path.join(root,'live-report.json');
 const report=fs.existsSync(reportPath)?JSON.parse(fs.readFileSync(reportPath)):{};
 const status={status:report.status||'LIVE_RECEIPT_INCOMPLETE',failure:report.failure||null,
  head,run,submitClicks:report.submitClicks??null,tourClicks:report.tourClicks??null,
  elapsedMs:report.elapsedMs??null,browserElapsedMs:report.browserElapsedMs??null,
  encryptedSha256:envelope.encryptedSha256,rawBodiesPublished:false};
 fs.writeFileSync(path.join(out,'public-status.json'),JSON.stringify(status,null,2)+'\n',{flag:'wx',mode:0o600});
 console.log(JSON.stringify({status:'EVIDENCE_ENCRYPTED',rawBodiesPublished:false,encryptedSha256:envelope.encryptedSha256}));
})().catch(()=>{console.error('EVIDENCE_ENCRYPTION_FAILED');process.exitCode=1;});
