
# Next Steps: WordPress FSM Loader – Phased Roadmap

This document builds on NEXTSTEPS.md by laying out a phased roadmap (MVP → Beta → Production) for delivering a robust WordPress loader around the PHP FSM library.

---

## 🚀 Phase 1: MVP (Foundations)

**Goal:** Prove viability of FSM-in-WP by wiring winzou/state-machine into a minimal loader.

**Deliverables**
- [ ] Composer + bundled vendor strategy documented.
- [ ] Basic loader (`wp-fsm-autoloader.php`) to initialize the FSM lib.
- [ ] Minimal API (`register_fsm_graph`, `apply_transition`, `get_state`).
- [ ] Postmeta storage adapter (state stored in `postmeta`).
- [ ] Transition log (basic array or option).
- [ ] Example graph: `media_upload` (idle → uploading → processing → done/failed).
- [ ] Unit test: validate allowed vs illegal transitions.

**Success Criteria**
- Developers can register a graph and apply transitions via one API call.
- Illegal transitions blocked with WP_Error.

---

## 🔧 Phase 2: Beta (Hardening & Observability)

**Goal:** Make FSM loader safe, observable, and flexible for more complex plugins.

**Deliverables**
- [ ] Capability + nonce guards integrated into transition gateway.
- [ ] Optimistic locking (`state` + `version` fields).
- [ ] Transition logger with persistent storage (custom table or structured postmeta).
- [ ] Observer hooks: `do_action` before/after transitions with context payload.
- [ ] Guard system pluggable with custom callbacks (WP style).
- [ ] Example plugin #2: Editorial workflow FSM (draft → review → publish → archived).
- [ ] Admin UI inspector: current state, allowed transitions, transition history.

**Success Criteria**
- Concurrency edge cases handled safely (double-click, cron/webhook overlap).
- Developers can build UIs driven by FSM state.

---

## 🧩 Phase 3: Production (Scale & Extensibility)

**Goal:** Production-ready, scalable FSM framework for WP ecosystem.

**Deliverables**
- [ ] Custom table storage adapter with schema: (`object_id`, `graph`, `state`, `version`, `updated_at`, `log`).
- [ ] Support for graph versioning and migration helpers (map legacy states).
- [ ] Retry/async handling with WP-Cron integration (e.g., `failed_retryable → retry`).
- [ ] Strict mode option: throw error if state mutated outside gateway.
- [ ] JSON export/import of transition history (for debugging, audits).
- [ ] Example plugin #3: WooCommerce order/payment extension using FSM loader.
- [ ] Documentation site: installation, API reference, migration guides.
- [ ] Extended unit & integration test suite (guards, observers, migration).

**Success Criteria**
- Loader handles production-scale workflows (orders, subscriptions, media pipelines).
- Widely usable by WP developers as a drop-in library.

---

## 🌟 Phase 4: Future / Stretch Goals

- [ ] Visual FSM editor in WP admin (drag/drop states + transitions).
- [ ] Integration with XState for frontend React flows in Gutenberg/Block Editor.
- [ ] Metrics + observability: expose transition counts, failure rates, retries.
- [ ] Petri Net / Statechart extensions (concurrency, hierarchy).
- [ ] “FSM-first” coding standard & linter for WP plugins.

---

## Summary

This roadmap allows the team to ship value incrementally:

- **Phase 1 (MVP):** working prototype, minimal FSM in WP.
- **Phase 2 (Beta):** safety, observability, real-world examples.
- **Phase 3 (Production):** scalability, migration, strictness, ecosystem readiness.
- **Phase 4 (Future):** advanced tooling, frontend integration, research directions.

