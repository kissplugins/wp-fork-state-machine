# 🎯 FSM Decision Matrix for WordPress Plugins

_When to use Finite State Machines in WordPress plugin development_

---

## 📚 FSM Primer

Finite State Machines (FSMs) are a foundational concept in **Computer Science**, typically introduced in undergraduate courses on **Automata Theory**, **Formal Languages**, and **Compiler Design**. They serve as one of the earliest models of computation, alongside pushdown automata and Turing machines.

### Formal Role in CS Curriculum
- **Automata Theory**: FSMs are used to define *regular languages*. Students learn how to model strings and patterns using deterministic (DFA) and nondeterministic (NFA) automata.
- **Regular Expressions**: Regex engines are often formally defined as FSMs under the hood.
- **Lexical Analysis**: Compilers use FSMs to tokenize source code.
- **Digital Circuit Design**: Sequential logic (flip-flops, counters, controllers) is modeled as FSMs.
- **Protocol Design**: Network protocols are validated with FSMs for request/response flows.

### Key Properties
- **States**: A finite set of discrete conditions.
- **Alphabet**: Input symbols the FSM reacts to.
- **Transition Function**: Defines allowed moves from one state to another.
- **Start State**: Where computation begins.
- **Accept States**: Where successful computations terminate.

### Why Important
FSMs give engineers a **precise mathematical tool** to reason about control flow, correctness, and complexity. Even though in WordPress plugin development FSMs are used more pragmatically (e.g., handling user flows, async jobs), the underlying rigor comes from this formal theory.

---

## 📊 Decision Matrix

| Scenario                         | Use FSM?   | Complexity | WordPress Context                          |
|----------------------------------|------------|------------|--------------------------------------------|
| Multi-step Forms/Wizards         | ✅ Yes     | Medium     | REST API + Gutenberg data stores            |
| Simple CRUD Operations           | ❌ No      | Low        | Use WP's hooks and filters                  |
| Order/Payment Processing         | ✅ Yes     | High       | WooCommerce status model, extend with subs  |
| Content Moderation Workflow      | ✅ Yes     | Medium     | Extends WP post_status system               |
| Simple Toggle Features           | ❌ No      | Low        | Use `wp_options` API                        |
| Media Upload with Processing     | ✅ Yes     | Medium     | Enhances WP Media Library                   |
| API Integration Sync             | ⚠️ Maybe   | Medium     | FSM if polling, retries, partial sync       |
| User Authentication Flow         | ✅ Yes     | High       | Gate transitions with capabilities/nonces   |
| Basic Data Display               | ❌ No      | Low        | Use WP's rendering pipeline                 |
| Subscription Management          | ✅ Yes     | High       | Billing cycle transitions                   |

---

## 🔧 State/Lifecycle Patterns in WordPress You Can Combine With FSMs

These aren’t FSMs themselves, but are useful as foundations and hooks:

- **Gutenberg Data Stores**: Redux-like via `@wordpress/data`. Great for UI state.
- **REST API Lifecycle Hooks**: e.g. `rest_pre_dispatch`, `rest_dispatch_request`. Guard or trigger FSM transitions.
- **Post Status Transitions**: Hooks like `transition_post_status` or `{old_status}_to_{new_status}`.
- **Comment Moderation States**: via `wp_set_comment_status`.
- **Cron & Background Jobs**: `wp_schedule_event` / `wp_schedule_single_event`. FSM for retries/time-based flows.
- **Customizer & Live Preview**: `customize_preview_init`. Draft/apply/commit style.
- **Storage & Auditing**: Keep a single `state` column + immutable transition log.

---

## ✅ When to USE FSMs in WordPress

| Indicator              | Description                                     | WP Example                                    |
|------------------------|-------------------------------------------------|-----------------------------------------------|
| Multiple Valid States  | 3+ states with distinct behaviors               | Post: draft → pending → published → archived   |
| Complex Transitions    | Guards, validations, side effects               | Woo order: pending → processing → completed   |
| Async Operations       | Loading, error, retry states                    | Media upload with optimization                |
| User Journey Tracking  | Track where users are in a process              | Membership flow                               |
| Rollback Requirements  | Revert/compensate safely                        | Migration with verify + rollback              |

⚠️ **Race Conditions & Retries**: Use row locks or version columns. Add retryable vs permanent failure states.

---

## ❌ When to AVOID FSMs in WordPress

| Indicator             | Description                       | Better Alternative                       |
|-----------------------|-----------------------------------|------------------------------------------|
| Simple Binary States  | Only on/off or true/false         | `update_option()` / `update_post_meta()`  |
| Stateless Operations  | Actions independent of state      | Action/filter hooks                      |
| One-time Actions      | Fire-and-forget                   | `wp_schedule_single_event()`             |
| Read-only Display     | No state changes                  | `WP_Query` + templates                   |
| Truly Tiny Feature    | FSM adds no clarity               | Keep procedural                          |

**Anti-patterns:**
- State implied by multiple flags (`is_paid`, `is_shipped`) → contradictions. Prefer one `state` column.
- Hidden transitions inside hooks → route all changes through a transition function.
- “Just a few if/else checks” → grows unbounded. Formalize early.

---

## 🧪 Quick Scorecard — Should You Use an FSM?

Check **3+ Yes** → Use FSM.

- [ ] 3+ distinct states with business meaning
- [ ] Transitions have guards (validation/permissions)
- [ ] Transitions trigger side effects (emails, webhooks, ledgers)
- [ ] Async or multi-actor flow (cron/webhooks/admin UI)
- [ ] Needs rollback/compensation or at-least-once delivery

---

## 🗺️ Example State Diagram — Media Upload Flow

```
 idle → uploading → processing → done
                ↘ failed_retryable → retrying → uploading …
```

---

## 🧩 Minimal Implementation Patterns

### PHP (backend FSM core)
```php
// PHP 8.1+
enum OrderState: string { case Draft='draft'; case Pending='pending'; case Processing='processing'; case Completed='completed'; case Failed='failed'; }

final class OrderFsm {
  public function __construct(private wpdb $db) {}

  public function transition(int $orderId, OrderState $from, OrderState $to, array $ctx=[]): void {
    if ($from === OrderState::Pending && $to === OrderState::Processing && empty($ctx['payment_valid'])) {
      throw new RuntimeException('Payment not validated');
    }
    $this->db->query('START TRANSACTION');
    $row = $this->db->get_row($this->db->prepare(
      "SELECT state, version FROM wp_orders WHERE id=%d FOR UPDATE", $orderId
    ));
    if ($row->state !== $from->value) throw new RuntimeException('Stale state');

    do_action('myplugin_fsm_before_transition', $orderId, $from, $to, $ctx);

    $this->db->update('wp_orders',
      ['state' => $to->value, 'version' => $row->version + 1, 'updated_at' => current_time('mysql', true)],
      ['id' => $orderId]
    );

    $this->db->query('COMMIT');
    do_action('myplugin_fsm_after_transition', $orderId, $from, $to, $ctx);
  }
}
```

### Editor/Admin (React)
```js
import { createMachine } from 'xstate';

export const uploadMachine = createMachine({
  id: 'upload',
  initial: 'idle',
  states: {
    idle: { on: { START: 'uploading' } },
    uploading: { on: { PROGRESS: 'uploading', SUCCESS: 'processing', FAILURE: 'retrying' } },
    processing: { on: { SUCCESS: 'done', FAILURE: 'retrying' } },
    retrying: { on: { RETRY: 'uploading', ABORT: 'failed' } },
    done: { type: 'final' },
    failed: { type: 'final' }
  }
});
```

---

## 💡 Real-World WordPress Plugin Examples

- ✅ Booking plugin: available → reserved → confirmed → completed → cancelled.
- ❌ Overkill FSM: simple contact form → just use validation + `wp_mail()`.
- ✅ Migration plugin: idle → scanning → migrating → verifying → complete (with rollback).
- ⚠️ Maybe FSM: cache plugin — FSM helps if warming/partial rebuilds.

---

## 🚀 Quick Decision Framework

- Can I draw a state diagram with 3+ states? → **Consider FSM**
- Are transitions conditional/complex? → **Use FSM**
- Do I need history/rollback? → **Use FSM**
- Is it just CRUD with hooks? → **Skip FSM**
- Managing async workflows? → **Use FSM**

---

## ❓ FAQ

**Q1: Is it easy to fall out of being disciplined about staying within the FSM or being FSM-centric/FSM-first?**  
Yes. It’s common to see developers add quick `if/else` checks or ad-hoc flags that bypass the FSM. This leads to hidden state transitions, contradictions, and bugs. Discipline means routing all transitions through the FSM layer, even if it feels heavier up front.

**Q2: Why should I have an FSM-first approach?**  
Because it enforces **clarity, consistency, and testability**. An FSM-first approach ensures every possible state and transition is explicit. This reduces unexpected edge cases, improves observability (via transition logs), and makes it easier to extend features without spaghetti logic.

**Q3: Give me an example of what a tough decision between keeping a function in the FSM or going outside of it?**  
A common dilemma: should an **email notification** be sent as part of the FSM transition, or triggered separately?  
- If sending the email is **integral** to the business process (e.g., confirming an order), it belongs inside the FSM transition so success/failure can affect state.  
- If it’s a **side convenience** (e.g., optional admin notification), it can be triggered as a listener outside the FSM.  
The rule of thumb: if failure to complete the action invalidates the state transition, keep it inside the FSM. Otherwise, decouple it.

**Q4: Is there a FSM already built into WP?**  
WordPress does not ship with a generic FSM library. However, several parts of WP core behave like FSMs:
- **Post Statuses** (`draft`, `pending`, `publish`, etc.) form a state model with defined transitions.
- **Comment Moderation** (`hold`, `approved`, `spam`, `trash`) is also a simple FSM.
- **Customizer Live Preview** (draft → preview → publish) is a state machine pattern.
These are not generalized FSMs, but domain-specific state flows.

**Q5: Is there a FSM system in WP that I can leverage for my own use?**  
You can extend or hook into WordPress’s existing state-like systems:
- **Post Status API**: register custom statuses (`register_post_status`) to build editorial workflows.
- **WooCommerce Order States**: already an FSM; extend with custom sub-states.
- **WP Cron Events**: scheduling and retries mimic FSM transitions.
- **Gutenberg Data Stores**: not an FSM but can complement one with centralized state.
If you want a **general FSM**, you’ll need to implement or import one (e.g., XState in JS, or a PHP FSM library), but WP’s patterns give you anchor points to integrate FSM thinking into plugin development.