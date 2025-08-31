<div class="fsm-demo">
  <header style="margin-bottom: 20px;">
    <h1 style="margin: 0 0 8px; font-size: 28px; color: #fff;">FSM Demo</h1>
    <p style="margin: 0 0 12px; color: #9ca3af;">Interactive finite state machine demonstration with decision-making tools</p>
    <div class="tech-stack">
      <p class="hint" style="margin: 0; padding: 8px 12px; background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 8px;">
        <strong>Tech Stack:</strong> Backend powered by <code>winzou/state-machine</code> library with WordPress REST API integration.
        Frontend features custom JavaScript FSM engine with async state synchronization and dual-mode operation (True FSM vs FSM-like).
      </p>
    </div>
  </header>

  <div class="instructions" style="margin-bottom: 20px; padding: 12px; background: rgba(34, 211, 238, 0.08); border: 1px solid rgba(34, 211, 238, 0.25); border-radius: 8px;">
    <p class="hint" style="margin: 0 0 8px; font-weight: 600; color: #22d3ee;">How to Use:</p>
    <p class="hint" style="margin: 0;">
      1. <strong>Score your scenario</strong> by checking relevant boxes in the Decision Maker panel
      2. Click <strong>"Explain"</strong> to see detailed reasoning below the buttons
      3. Click <strong>"Apply to Simulator"</strong> to automatically set the FSM mode based on your score
      4. Test state transitions in the FSM Simulator panel using the event buttons
    </p>
  </div>

  <div class="grid" id="app">
    <!-- Decision Maker -->
    <section class="card" id="decision-card" aria-labelledby="decision-title">
      <h2 id="decision-title">Decision Maker</h2>
      <div class="body">
        <div class="hint">Score the scenario. If you check <strong>3+</strong>, prefer an FSM.</div>
        <div class="list" id="scorecard">
          <label class="row"><input type="checkbox" data-key="hasStates"> <span>3+ distinct states with business meaning</span></label>
          <label class="row"><input type="checkbox" data-key="hasGuards"> <span>Transitions have guards (validation/permissions)</span></label>
          <label class="row"><input type="checkbox" data-key="hasEffects"> <span>Transitions trigger side effects (emails/webhooks/ledgers)</span></label>
          <label class="row"><input type="checkbox" data-key="isAsync"> <span>Async or multi-actor (cron/webhooks/admin UI)</span></label>
          <label class="row"><input type="checkbox" data-key="needsRollback"> <span>Needs rollback/compensation or at-least-once delivery</span></label>
        </div>
        <div class="divider"></div>
        <div class="hint">Signals to avoid an FSM (strong negatives)</div>
        <div class="list">
          <label class="row"><input type="checkbox" data-key="isBinary"> <span>Simple binary toggle</span></label>
          <label class="row"><input type="checkbox" data-key="isStateless"> <span>Stateless operation (no history)</span></label>
          <label class="row"><input type="checkbox" data-key="isOneShot"> <span>One-time fire-and-forget</span></label>
          <label class="row"><input type="checkbox" data-key="isReadOnly"> <span>Read-only display</span></label>
          <label class="row"><input type="checkbox" data-key="isTiny"> <span>Truly tiny feature where FSM adds no clarity</span></label>
        </div>
        <div class="divider"></div>
        <div class="row wrap">
          <div id="decision-pill" class="pill warn" aria-live="polite">Result: TBD</div>
          <button class="btn-ghost" id="explain-btn" title="Explain decision">Explain</button>
          <button class="btn-brand" id="apply-btn" title="Apply to simulator">Apply to Simulator</button>
          <button id="reset-score" class="btn">Reset</button>
        </div>
        <div id="decision-explain" class="hint" style="margin-top:8px; padding: 8px; background: rgba(99, 102, 241, 0.05); border-radius: 6px; min-height: 20px; font-style: italic;" aria-live="polite"></div>
      </div>
    </section>

    <!-- FSM Simulator -->
    <section class="card" id="sim-card" aria-labelledby="sim-title">
      <h2 id="sim-title">FSM Simulator (Upload Flow)</h2>
      <div class="body">
        <div class="row wrap" style="justify-content:space-between; gap:12px;">
          <div class="row" role="group" aria-label="Mode">
            <span class="muted">Mode:</span>
            <label class="switch"><input type="checkbox" id="mode-toggle"><span class="muted">&nbsp;</span></label>
            <span id="mode-label" class="badge" title="True FSM enforces transitions; FSM-like allows direct mutations">True FSM</span>
          </div>
          <div class="kpi">
            <div class="box"><h4>Current State</h4><div class="v" id="state-badge">idle</div></div>
            <div class="box"><h4>Allowed Events</h4><div class="v" id="allowed-events">START</div></div>
            <div class="box"><h4>Last Event</h4><div class="v" id="last-event">—</div></div>
          </div>
        </div>

        <div class="divider"></div>
        <div class="hint">Events</div>
        <div class="controls" id="event-buttons" role="group" aria-label="Events">
          <button data-ev="START" class="btn-brand">START</button>
          <button data-ev="PROGRESS">PROGRESS</button>
          <button data-ev="SUCCESS">SUCCESS</button>
          <button data-ev="FAIL_TEMP">FAIL_TEMP</button>
          <button data-ev="FAIL_PERM" class="btn-danger">FAIL_PERM</button>
          <button data-ev="RETRY">RETRY</button>
          <button data-ev="ABORT" class="btn-danger">ABORT</button>
          <button data-ev="RESET" class="btn">RESET</button>
        </div>

        <div class="divider"></div>
        <div class="hint">Guards (simulate prerequisites)</div>
        <div class="row wrap">
          <label class="switch"><input type="checkbox" id="g-file-ok" checked><span></span></label><span>File type OK (needed for <code>uploading → processing</code>)</span>
        </div>
        <div class="row wrap" style="margin-top:6px">
          <label class="switch"><input type="checkbox" id="g-optimize-ok"><span></span></label><span>Optimization complete (needed for <code>processing → done</code>)</span>
        </div>

        <div class="divider"></div>
        <div class="row wrap">
          <button id="double-fire" class="btn">Simulate Two Events at Once</button>
          <button id="clear-log" class="btn-ghost">Clear Log</button>
          <button id="export-json" class="btn-ghost">Export JSON</button>
        </div>
      </div>
    </section>

    <!-- Behind the Scenes -->
    <section class="card" id="status-card" aria-labelledby="status-title">
      <h2 id="status-title">Behind the Scenes</h2>
      <div class="body">
        <div class="kv"><div>Lock</div><div id="lock-status">unlocked</div></div>
        <div class="kv"><div>Queue</div><div id="queue-size">0</div></div>
        <div class="kv"><div>Observers</div><div>
          <label class="row"><input type="checkbox" id="obs-email" checked> <span>Send email on <code>done</code></span></label>
          <label class="row"><input type="checkbox" id="obs-webhook" checked> <span>Emit webhook on any transition</span></label>
          <label class="row"><input type="checkbox" id="obs-job"> <span>Enqueue job on <code>processing</code></span></label>
        </div></div>
        <div class="divider"></div>
        <div class="log" id="log" aria-live="polite" aria-label="Transition log"></div>
      </div>
    </section>
  </div>
</div>