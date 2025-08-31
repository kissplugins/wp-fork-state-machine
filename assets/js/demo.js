(function(){
    const ssKey = 'fsm-demo-v1';

    function now(){return new Date().toLocaleTimeString()}
    function el(id){return document.getElementById(id)}
    function ceil(n){return Math.max(0,Math.ceil(n))}

    // API configuration
    const apiConfig = window.fsm_demo_data || {
        api_url: '/wp-json/kiss-fsm/v1/',
        nonce: ''
    };

    let currentJobId = null;

    // --- FSM definition (kept entirely in JS for this session) ---
    const fsmConfig = {
      states: ['idle','uploading','processing','failed_retryable','retrying','done','failed_permanent'],
      initial: 'idle',
      transitions: {
        idle: { START: 'uploading', RESET: 'idle' },
        uploading: { PROGRESS: 'uploading', SUCCESS: 'processing', FAIL_TEMP: 'failed_retryable', FAIL_PERM: 'failed_permanent', ABORT: 'failed_permanent', RESET: 'idle' },
        processing: { SUCCESS: 'done', FAIL_TEMP: 'failed_retryable', ABORT: 'failed_permanent', RESET: 'idle' },
        failed_retryable: { RETRY: 'uploading', RESET: 'idle' },
        retrying: { /* example placeholder if needed */ RESET: 'idle' },
        done: { RESET: 'idle' },
        failed_permanent: { RESET: 'idle' }
      },
      guards: {
        'uploading->processing': ()=> el('g-file-ok').checked ? {ok:true} : {ok:false, msg:'File type not allowed'},
        'processing->done': ()=> el('g-optimize-ok').checked ? {ok:true} : {ok:false, msg:'Optimization not complete'}
      }
    };

    // --- Logger ---
    const logEl = el('log');
    const log = [];
    function logPush(type, msg, extra){
      const entry = { ts: Date.now(), type, msg, extra };
      log.unshift(entry);
      if(log.length>400) log.pop();
      renderLog();
      persist();
    }
    function renderLog(){
      logEl.innerHTML = log.map(e=>`<div class="i"><span class="ts">[${new Date(e.ts).toLocaleTimeString()}]</span> <span class="type-${e.type}">${e.type.toUpperCase()}</span> — ${e.msg}${e.extra?` <span class="muted">${escapeHtml(JSON.stringify(e.extra))}</span>`:''}`).join('');
    }

    // --- FSM engine ---
    const engine = {
      state: fsmConfig.initial,
      locked: false,
      queue: [],
      modeTrue: true, // true = True FSM; false = FSM-like
      async dispatch(event){
        if(this.locked){ logPush('warn', `Engine locked; queued event ${event}`); this.queue.push(event); updateStatus(); return; }
        this.locked = true; updateStatus();
        const from = this.state;
        const next = (fsmConfig.transitions[from]||{})[event];
        const allowed = next !== undefined;

        if(!this.modeTrue){
          // FSM-like: allow any mutation; pick next if defined, else mutate to event name as a pretend state (demo of danger)
          if(allowed){
            if(!this.guardOK(from,next,event,false)) { /* warn only */ }
            this.commit(next, event, {allowed:true});
          } else {
            // simulate a direct mutation risk
            logPush('warn', `Illegal event '${event}' from '${from}' — mutating anyway (FSM-like)`);
            this.commit(from, event, {mutated:true}); // stay put but show risk
          }
        } else {
          // True FSM mode - call backend API
          if(!allowed){
            this.locked=false;
            updateStatus();
            logPush('err', `Illegal event '${event}' from '${from}' (blocked)`);
            this.drain();
            return;
          }

          const ok = this.guardOK(from,next,event,true);
          if(!ok){
            this.locked=false;
            updateStatus();
            this.drain();
            return;
          }

          // Call backend API for true FSM mode
          try {
            await this.callBackendTransition(event);
          } catch (error) {
            logPush('err', `Backend transition failed: ${error.message}`);
            this.locked = false;
            updateStatus();
            this.drain();
          }
        }
      },

      async callBackendTransition(event) {
        if (!currentJobId) {
          // Create a new job first
          currentJobId = await this.createJob();
          logPush('info', `Created new job: ${currentJobId}`);
        }

        const response = await fetch(`${apiConfig.api_url}jobs/${currentJobId}/transition`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': apiConfig.nonce
          },
          body: JSON.stringify({ event })
        });

        if (!response.ok) {
          const error = await response.json();
          throw new Error(error.message || `HTTP ${response.status}`);
        }

        const result = await response.json();
        logPush('info', `Backend transition: ${result.from} → ${result.to}`, result);

        // Update frontend state to match backend
        this.commit(result.to, event, {
          allowed: true,
          backend: true,
          from: result.from,
          transition: result.transition
        });
      },

      async createJob() {
        const response = await fetch(`${apiConfig.api_url}jobs`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': apiConfig.nonce
          }
        });

        if (!response.ok) {
          throw new Error(`Failed to create job: HTTP ${response.status}`);
        }

        const result = await response.json();
        return result.id;
      },
      guardOK(from,to,event,blocking){
        const key = `${from}->${to}`;
        if(fsmConfig.guards[key]){
          const g = fsmConfig.guards[key]();
          if(!g.ok){
            if(blocking){ logPush('err', `Guard failed for ${from} → ${to}: ${g.msg}`); }
            else { logPush('warn', `Guard would fail (${from} → ${to}): ${g.msg}`); }
            return false;
          }
        }
        return true;
      },
      commit(to,event,meta){
        const from = this.state;
        this.state = to;
        updateUiState(from,event,to);
        // observers
        if(el('obs-webhook').checked){ logPush('info', `Webhook emitted`, {from,to,event}); }
        if(el('obs-email').checked && to==='done'){ logPush('info', `Email sent: Upload Complete`, {to}); }
        if(el('obs-job').checked && to==='processing'){ logPush('info', `Job enqueued: optimize assets`, {to}); }
        this.locked = false; updateStatus(); this.drain(); persist();
      },
      drain(){
        if(this.queue.length===0) return;
        const e = this.queue.shift();
        logPush('info', `Draining queued event ${e}`);
        this.dispatch(e);
      }
    };

    // --- UI wiring ---
    const stateBadge = el('state-badge');
    const lastEvent = el('last-event');
    const allowedEvents = el('allowed-events');
    const lockStatus = el('lock-status');
    const queueSize = el('queue-size');

    function updateStatus(){
      lockStatus.textContent = engine.locked? 'locked':'unlocked';
      queueSize.textContent = String(engine.queue.length);
    }

    function updateButtons(){
      const btns = document.querySelectorAll('#event-buttons button');
      const allowed = fsmConfig.transitions[engine.state] || {};
      btns.forEach(b=>{
        const ev = b.dataset.ev;
        const isAllowed = !!allowed[ev];
        if(engine.modeTrue){
          // in True FSM mode, disable disallowed buttons except RESET
          b.disabled = !(isAllowed || ev==='RESET' || ev==='PROGRESS');
          b.title = b.disabled? 'Not allowed in this state' : '';
        } else {
          b.disabled = false; b.title = '';
        }
      });
      allowedEvents.textContent = Object.keys(allowed).join(', ') || '—';
    }

    function updateUiState(from,event,to){
      stateBadge.textContent = engine.state;
      lastEvent.textContent = event || '—';
      updateButtons();
      if(event){ logPush('info', `${from} → ${to} via ${event}`); }
    }

    // Event buttons
    document.querySelectorAll('#event-buttons button').forEach(btn=>{
      btn.addEventListener('click', async ()=> {
        try {
          await engine.dispatch(btn.dataset.ev);
        } catch (error) {
          logPush('err', `Event dispatch failed: ${error.message}`);
        }
      });
    });

    // Mode toggle
    el('mode-toggle').addEventListener('change', (e)=>{
      engine.modeTrue = !e.target.checked; // checked = FSM-like
      el('mode-label').textContent = engine.modeTrue? 'True FSM':'FSM-like';
      logPush('info', `Mode set to ${engine.modeTrue? 'True FSM':'FSM-like'}`);

      // Reset job when switching modes
      if (engine.modeTrue && currentJobId) {
        currentJobId = null;
        logPush('info', 'Job reset for True FSM mode');
      }

      updateButtons();
      persist();
    });

    // Double fire
    el('double-fire').addEventListener('click', async ()=>{
      // attempt to fire SUCCESS and FAIL_TEMP at same time
      try {
        await engine.dispatch('SUCCESS');
        setTimeout(async ()=> {
          try {
            await engine.dispatch('FAIL_TEMP');
          } catch (error) {
            logPush('err', `Second dispatch failed: ${error.message}`);
          }
        }, 0);
      } catch (error) {
        logPush('err', `First dispatch failed: ${error.message}`);
      }
    });

    // Clear log & export
    el('clear-log').addEventListener('click', ()=>{ log.length=0; renderLog(); });
    el('export-json').addEventListener('click', ()=>{
      const blob = new Blob([JSON.stringify({state:engine.state,log,config:fsmConfig}, null, 2)], {type:'application/json'});
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a'); a.href = url; a.download = 'fsm-session.json'; a.click(); URL.revokeObjectURL(url);
    });

    // --- Decision maker logic ---
    const scoreDefaults = {hasStates:false,hasGuards:false,hasEffects:false,isAsync:false,needsRollback:false,isBinary:false,isStateless:false,isOneShot:false,isReadOnly:false,isTiny:false};
    const scoreInputs = document.querySelectorAll('#scorecard input, #decision-card .list input');

    function computeDecision(){
      const s = readScores();
      const positives = ['hasStates','hasGuards','hasEffects','isAsync','needsRollback'].filter(k=>s[k]).length;
      const negatives = ['isBinary','isStateless','isOneShot','isReadOnly','isTiny'].filter(k=>s[k]).length;
      let verdict='Maybe', style='warn', explain=[];
      if(positives>=3 && negatives<=1){ verdict='Use FSM'; style='ok'; }
      else if(positives<=1 || negatives>=3){ verdict='Avoid FSM'; style='err'; }
      else { verdict='Maybe'; style='warn'; }
      if(positives>0) explain.push(`Positives: ${positives} (${listChecked(['hasStates','hasGuards','hasEffects','isAsync','needsRollback'], s)})`);
      if(negatives>0) explain.push(`Negatives: ${negatives} (${listChecked(['isBinary','isStateless','isOneShot','isReadOnly','isTiny'], s)})`);
      return {verdict, style, explain: explain.join(' · ')};
    }

    function listChecked(keys, s){
      const map={hasStates:'3+ states',hasGuards:'guards',hasEffects:'side effects',isAsync:'async/multi-actor',needsRollback:'rollback',isBinary:'binary',isStateless:'stateless',isOneShot:'one-shot',isReadOnly:'read-only',isTiny:'tiny'};
      return keys.filter(k=>s[k]).map(k=>map[k]).join(', ');
    }

    function readScores(){
      const saved = load().scores || {};
      const s = Object.assign({}, scoreDefaults, saved);
      // sync from DOM
      document.querySelectorAll('#decision-card input[type="checkbox"]').forEach(inp=>{
        const key = inp.dataset.key; if(key){ s[key] = inp.checked; }
      });
      return s;
    }

    function writeScores(s){
      document.querySelectorAll('#decision-card input[type="checkbox"]').forEach(inp=>{
        const key = inp.dataset.key; if(key){ inp.checked = !!s[key]; }
      });
    }

    function updateDecision(){
      const {verdict, style, explain} = computeDecision();
      const pill = el('decision-pill');
      pill.className = `pill ${style}`;
      pill.textContent = `Result: ${verdict}`;
      el('decision-explain').textContent = explain || 'Check some boxes above and click "Explain" to see detailed reasoning';
    }

    scoreInputs.forEach(inp=> inp.addEventListener('change', ()=>{ updateDecision(); persist(); }));
    el('explain-btn').addEventListener('click', updateDecision);
    el('apply-btn').addEventListener('click', ()=>{
      const v = computeDecision().verdict;
      if(v==='Use FSM'){ el('mode-toggle').checked = false; el('mode-toggle').dispatchEvent(new Event('change')); }
      if(v==='Avoid FSM'){ el('mode-toggle').checked = true; el('mode-toggle').dispatchEvent(new Event('change')); }
      logPush('info', `Decision applied to simulator: ${v}`);
    });
    el('reset-score').addEventListener('click', ()=>{ writeScores(scoreDefaults); updateDecision(); persist(); });

    // --- Persistence ---
    function persist(){
      const data = {
        state: engine.state,
        modeTrue: engine.modeTrue,
        jobId: currentJobId,
        scores: readScores(),
        guards: { fileOk: el('g-file-ok').checked, optimizeOk: el('g-optimize-ok').checked },
        observers: { email: el('obs-email').checked, webhook: el('obs-webhook').checked, job: el('obs-job').checked },
        log
      };
      sessionStorage.setItem(ssKey, JSON.stringify(data));
    }
    function load(){
      try{ return JSON.parse(sessionStorage.getItem(ssKey)||'{}'); }catch(e){ return {}; }
    }

    function restore(){
      const data = load();
      if(data && data.state){ engine.state = data.state; }
      if(data.jobId){ currentJobId = data.jobId; }
      if(typeof data.modeTrue==='boolean'){ engine.modeTrue = data.modeTrue; el('mode-toggle').checked = !engine.modeTrue; el('mode-label').textContent = engine.modeTrue? 'True FSM':'FSM-like'; }
      if(data.guards){ el('g-file-ok').checked = !!data.guards.fileOk; el('g-optimize-ok').checked = !!data.guards.optimizeOk; }
      if(data.observers){ el('obs-email').checked = !!data.observers.email; el('obs-webhook').checked = !!data.observers.webhook; el('obs-job').checked = !!data.observers.job; }
      if(Array.isArray(data.log)){ log.splice(0, log.length, ...data.log); renderLog(); }
      writeScores(Object.assign({}, scoreDefaults, data.scores||{}));
      updateUiState(null,null,engine.state); updateStatus(); updateDecision();
    }

    // --- Utilities ---
    function escapeHtml(s){ return s && s.replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

    // --- Initialize ---
    restore();
  })();