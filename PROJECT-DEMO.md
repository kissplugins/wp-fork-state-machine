
# PROJECT-DEMO.md — Build the WordPress FSM Decision Maker Demo (Shortcode)

This document is a **standalone reference** for building a WordPress plugin that demonstrates **Finite State Machines (FSMs)** through an interactive **Decision Maker demo**. It includes all necessary context, background, and sample code — so no external documents or prior knowledge of our static demos are required.

---

## 0) Background: Why FSMs in WordPress?

Finite State Machines (FSMs) are formal models of computation used to manage systems with distinct states and explicit transitions between them. In WordPress plugin development, FSMs can be used for:

- **Multi-step workflows** (onboarding, setup wizards)
- **E-commerce flows** (orders, payments, subscriptions)
- **Editorial processes** (draft → review → publish → archive)
- **Async tasks** (uploads, migrations, cron jobs)

FSMs provide **clarity, testability, and safety** by preventing illegal transitions and making all state changes explicit.

---

## 1) Project Objectives

1. Package an **interactive FSM Decision Maker** demo as a WordPress plugin.  
2. Expose the demo via a shortcode `[fsm_decision_demo]`.  
3. Demonstrate both **True FSM** (enforced transitions) and **FSM-like** (looser, conventional WP style).  
4. Leverage a backend PHP FSM library (e.g., forked from `winzou/state-machine`) as the authoritative FSM engine.  
5. Provide a client-side JS FSM for responsiveness, synchronized with the backend.

---

## 2) Do We Need a Front-End FSM?

Yes — for responsiveness and pedagogy. The architecture will be **hybrid**:

- **Frontend FSM (JavaScript):** Updates UI instantly, logs transitions, simulates race conditions and guards.  
- **Backend FSM (PHP library):** Validates transitions via REST API; acts as the source of truth.  
- **Sync Pattern:** UI applies a transition locally, posts it to the server. If the server rejects it (guard failure, illegal transition), the UI rolls back and displays a warning.

This shows both the **discipline of FSMs** and the risks of bypassing them.

---

## 3) Plugin Structure

```
wp-content/plugins/
└─ fsm-decision-demo/
   ├─ fsm-decision-demo.php                # Plugin bootstrap
   ├─ readme.txt                           # Optional WP.org readme
   ├─ inc/
   │  ├─ Shortcodes.php                    # Registers/render shortcodes
   │  ├─ Rest.php                          # REST routes for transitions
   │  ├─ Store.php                         # Persistence adapter
   │  ├─ Graphs.php                        # FSM graph definitions
   │  └─ Engine.php                        # Wrapper around FSM library
   ├─ templates/
   │  └─ shortcode-demo.php                # Container markup for demo
   ├─ assets/
   │  ├─ css/demo.css                      # Styles scoped to .fsm-demo
   │  └─ js/demo.js                        # Interactive FSM + UI logic
   └─ vendor/                              # Composer vendor dir (if used)
```

---

## 4) Backend FSM (PHP)

We define a demo FSM called **media_upload** with the following states and transitions:

**States:**
- `idle`
- `uploading`
- `processing`
- `failed_retryable`
- `failed_permanent`
- `done`

**Transitions:**
- `START: idle → uploading`
- `SUCCESS: uploading → processing` (guard: file type OK)
- `SUCCESS: processing → done` (guard: optimization complete)
- `FAIL_TEMP: (uploading|processing) → failed_retryable`
- `RETRY: failed_retryable → uploading`
- `ABORT: * → failed_permanent`
- `RESET: * → idle`

**Sample Graph Definition (inc/Graphs.php):**
```php
<?php
namespace KissPlugins\FsmDemo;

class Graphs {
  public static function media_upload() {
    return [
      'states' => ['idle','uploading','processing','failed_retryable','failed_permanent','done'],
      'transitions' => [
        'start' => ['from' => ['idle'], 'to' => 'uploading'],
        'success_upload' => ['from' => ['uploading'], 'to' => 'processing'],
        'success_process' => ['from' => ['processing'], 'to' => 'done'],
        'fail_temp' => ['from' => ['uploading','processing'], 'to' => 'failed_retryable'],
        'retry' => ['from' => ['failed_retryable'], 'to' => 'uploading'],
        'abort' => ['from' => ['idle','uploading','processing'], 'to' => 'failed_permanent'],
        'reset' => ['from' => ['idle','uploading','processing','failed_retryable','failed_permanent','done'], 'to' => 'idle'],
      ]
    ];
  }
}
```

---

## 5) Persistence Layer

For demo purposes, create a **custom table**:

```sql
CREATE TABLE wp_fsm_demo_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  state VARCHAR(64) NOT NULL,
  version BIGINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  log LONGTEXT NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
```

- `state` = current FSM state  
- `version` = optimistic locking counter  
- `log` = JSON transition history  

**Sample Store.php (simplified):**
```php
<?php
namespace KissPlugins\FsmDemo;

class Store {
  public static function createJob($wpdb) {
    $wpdb->insert("{$wpdb->prefix}fsm_demo_jobs", [
      'state' => 'idle',
      'version' => 0,
      'updated_at' => current_time('mysql', 1),
      'log' => json_encode([])
    ]);
    return $wpdb->insert_id;
  }

  public static function getJob($wpdb, $id) {
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}fsm_demo_jobs WHERE id=%d", $id));
  }
}
```

---

## 6) REST API Endpoints

Namespace: `kiss-fsm/v1`

- `POST /jobs` → create job (`{ id }`)
- `GET /jobs/{id}` → get job state, allowed transitions
- `POST /jobs/{id}/transition` → apply transition  
  Request body: `{ event: "START"|"SUCCESS"|..., version, context }`  
  Response: `{ from, to, state, allowed, log[] }`

**Sample REST Handler (inc/Rest.php excerpt):**
```php
register_rest_route('kiss-fsm/v1', '/jobs/(?P<id>\d+)/transition', [
  'methods' => 'POST',
  'callback' => function($req) use ($wpdb) {
    $id = intval($req['id']);
    $event = sanitize_text_field($req['event']);
    // Load job + validate transition with Engine
    // Save state + append log
    return [ 'id'=>$id, 'event'=>$event, 'state'=>'processing', 'allowed'=>['success','abort'] ];
  },
  'permission_callback' => function() { return current_user_can('read'); }
]);
```

---

## 7) Frontend FSM (JavaScript)

The frontend replicates the same states and transitions. It manages:

- Current state badge  
- Buttons for allowed events  
- Log panel (with `aria-live`)  
- Mode toggle: **True FSM**, **FSM-like**, **Server-authoritative**

**Sample JS (assets/js/demo.js excerpt):**
```js
(function(){
  const fsmConfig = {
    idle: { START: 'uploading', RESET: 'idle' },
    uploading: { SUCCESS: 'processing', FAIL_TEMP: 'failed_retryable', ABORT: 'failed_permanent' },
    processing: { SUCCESS: 'done', FAIL_TEMP: 'failed_retryable', ABORT: 'failed_permanent' },
    failed_retryable: { RETRY: 'uploading', RESET: 'idle' },
    done: { RESET: 'idle' },
    failed_permanent: { RESET: 'idle' }
  };

  let state = 'idle';
  const log = [];

  function transition(event) {
    const next = (fsmConfig[state]||{})[event];
    if (!next) {
      addLog('Invalid transition ' + state + ' → ('+event+')');
      return;
    }
    addLog(state + ' → ' + next + ' via ' + event);
    state = next;
    render();
  }

  function addLog(msg) {
    log.unshift({ ts: Date.now(), msg });
    renderLog();
  }

  function renderLog() {
    document.getElementById('fsm-log').innerHTML = log.map(l =>
      `<div>[${new Date(l.ts).toLocaleTimeString()}] ${l.msg}</div>`
    ).join('');
  }

  function render() {
    document.getElementById('fsm-state').textContent = state;
  }

  window.fsmDemo = { transition };
  render();
})();
```

---

## 8) Shortcode Implementation

**Shortcodes.php:**
```php
<?php
namespace KissPlugins\FsmDemo;

class Shortcodes {
  public static function init() {
    add_shortcode('fsm_decision_demo', [__CLASS__, 'renderDemo']);
  }

  public static function renderDemo($atts=[]) {
    wp_enqueue_style('fsm-demo-css', FSM_DEMO_URL.'assets/css/demo.css', [], FSM_DEMO_VERSION);
    wp_enqueue_script('fsm-demo-js', FSM_DEMO_URL.'assets/js/demo.js', [], FSM_DEMO_VERSION, true);
    return file_get_contents(FSM_DEMO_DIR.'templates/shortcode-demo.php');
  }
}
```

**templates/shortcode-demo.php:**
```html
<div class="fsm-demo">
  <h3>FSM Decision Maker Demo</h3>
  <div>Current State: <span id="fsm-state">idle</span></div>
  <div class="fsm-controls">
    <button onclick="fsmDemo.transition('START')">Start</button>
    <button onclick="fsmDemo.transition('SUCCESS')">Success</button>
    <button onclick="fsmDemo.transition('FAIL_TEMP')">Fail (Temp)</button>
    <button onclick="fsmDemo.transition('RETRY')">Retry</button>
    <button onclick="fsmDemo.transition('ABORT')">Abort</button>
    <button onclick="fsmDemo.transition('RESET')">Reset</button>
  </div>
  <div id="fsm-log" aria-live="polite" style="max-height:200px;overflow:auto;"></div>
</div>
```

---

## 9) CSS Styling (assets/css/demo.css)

```css
.fsm-demo { background:#0b1020; color:#e5e7eb; padding:1rem; border-radius:10px; }
.fsm-demo h3 { margin-top:0; }
.fsm-controls { margin:1rem 0; display:flex; flex-wrap:wrap; gap:6px; }
.fsm-controls button { background:#1e293b; color:#fff; border:1px solid #334155; padding:6px 10px; border-radius:6px; cursor:pointer; }
.fsm-controls button:hover { background:#334155; }
#fsm-log { font-family: monospace; font-size: 13px; margin-top: 1rem; }
```

---

## 10) Acceptance Criteria

- `[fsm_decision_demo]` renders an interactive demo with state, controls, and log.  
- Valid transitions update state; invalid ones log warnings.  
- REST endpoints (optional for demo) validate and persist server state.  
- Plugin activates without fatal errors.  

---

## 11) Future Enhancements

- Admin “FSM Inspector” page with job list and transition history.  
- Support for multiple graphs (orders, subscriptions, workflows).  
- Graph visualizer (SVG diagram).  
- Integration with Gutenberg blocks.  

---

## 12) Checklist

- [ ] Generate plugin skeleton and bootstrap.  
- [ ] Implement `Graphs.php`, `Store.php`, `Engine.php`.  
- [ ] Implement REST endpoints.  
- [ ] Port demo HTML/CSS/JS.  
- [ ] Wire shortcode + enqueue assets.  
- [ ] Test transitions: happy path, invalid, retry.  
- [ ] Package `v0.1.0`.  

---

This document contains everything an LLM or developer needs to build the FSM Decision Maker demo plugin as a shortcode-based WordPress demo.
