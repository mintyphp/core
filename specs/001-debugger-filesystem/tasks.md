---
description: "Task list for Debugger Filesystem Storage implementation"
---

# Tasks: Debugger Filesystem Storage

**Input**: Design documents from `/specs/001-debugger-filesystem/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

**Tests**: Tests are OPTIONAL in this implementation - only included if explicitly requested by the user in future iterations.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `- [ ] [ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- Single project structure: `src/Core/`, `tests/Core/` at repository root
- Facades auto-generated: `src/` (regenerate via `generate_wrappers.php`)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and filesystem storage foundation

- [X] T001 Add storage path configuration to src/Core/Debugger.php (public static $__storagePath)
- [X] T002 Add browser session cookie constant to src/Core/Debugger.php (COOKIE_NAME = 'minty_debug_session')
- [X] T003 [P] Create utility method generateUUID() in src/Core/Debugger.php for RFC 4122 v4 UUID generation
- [X] T004 [P] Create utility method getBrowserSessionId() in src/Core/Debugger.php for cookie-based session tracking
- [X] T005 [P] Create utility method getStoragePath() in src/Core/Debugger.php to resolve storage directory path
- [X] T006 [P] Create utility method ensureDirectory() in src/Core/Debugger.php for directory creation with error handling

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core filesystem operations that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T007 Implement atomicWrite() method in src/Core/Debugger.php (write-then-rename pattern)
- [X] T008 Implement getRequestPath() method in src/Core/Debugger.php to generate file path for UUID
- [X] T009 Implement getHistoryPath() method in src/Core/Debugger.php to generate history file path
- [X] T010 Implement readHistory() method in src/Core/Debugger.php with file locking (flock LOCK_SH)
- [X] T011 Implement writeHistory() method in src/Core/Debugger.php with file locking (flock LOCK_EX)
- [X] T012 Implement updateHistory() method in src/Core/Debugger.php to add UUID and enforce limit
- [X] T013 Implement deleteRequestFile() method in src/Core/Debugger.php to remove old request files

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Debug Request Inspection Without Session Pollution (Priority: P1) 🎯 MVP

**Goal**: Eliminate session pollution by storing debug data on filesystem instead of $_SESSION, and ensure debugger works independently of PHP sessions

**Independent Test**: Enable debugger without starting session, make a request, verify debugger captures and displays request data correctly (no 'debugger' key in session inspection)

### Implementation for User Story 1

- [X] T014 [US1] Modify end() method in src/Core/Debugger.php to generate UUID and store request data to filesystem
- [X] T015 [US1] Modify end() method in src/Core/Debugger.php to call updateHistory() instead of array operations on $_SESSION
- [X] T016 [US1] Modify end() method in src/Core/Debugger.php to handle filesystem write errors gracefully (log and continue)
- [X] T017 [US1] Remove $_SESSION[$this->sessionKey] array operations from end() method in src/Core/Debugger.php
- [X] T018 [US1] Implement getBrowserSessionId() cookie setting logic in constructor or first call in src/Core/Debugger.php
- [X] T019 [US1] Modify toolbar() method in src/Core/Debugger.php to read request data from filesystem instead of $_SESSION
- [X] T020 [US1] Update toolbar() method in src/Core/Debugger.php to handle missing history file (return empty array)
- [X] T021 [US1] Update toolbar() method in src/Core/Debugger.php to load request files by UUID from history
- [X] T022 [US1] Add error handling in toolbar() method for corrupted/missing request files in src/Core/Debugger.php
- [X] T023 [US1] Remove getSessionKey() method usage from session inspection in src/Core/Debugger.php
- [X] T024 [US1] Verify getSessionData() method no longer references $this->sessionKey for debugger filtering in src/Core/Debugger.php
- [X] T025 [US1] Update __construct() to initialize storage directory if enabled in src/Core/Debugger.php
- [X] T026 [US1] Regenerate facade wrapper by running php generate_wrappers.php in repository root

**Checkpoint**: At this point, User Story 1 should be fully functional - debugger stores data on filesystem, no session pollution, works without PHP sessions

---

## Phase 4: User Story 2 - Persistent Debug History Across Requests (Priority: P2)

**Goal**: Enable investigation of issues from previous browser sessions by persisting debug history on filesystem

**Independent Test**: Make requests in browser session A, close browser and start new browser session B, verify session A's debug history is still accessible via filesystem

### Implementation for User Story 2

- [X] T027 [US2] Verify getBrowserSessionId() generates and stores session cookie with proper attributes (HttpOnly, SameSite) in src/Core/Debugger.php
- [X] T028 [US2] Verify getBrowserSessionId() validates existing cookie format (32 chars, alphanumeric + - _) in src/Core/Debugger.php
- [X] T029 [US2] Ensure history files are named with session identifier (session-{id}.json) in getHistoryPath() in src/Core/Debugger.php
- [X] T030 [US2] Ensure request directories are named with session identifier ({id}/) in getRequestPath() in src/Core/Debugger.php
- [ ] T031 [US2] Verify toolbar() method can access and display multiple browser session histories in src/Core/Debugger.php
- [ ] T032 [US2] Add method listBrowserSessions() in src/Core/Debugger.php to enumerate available session directories
- [ ] T033 [US2] Update toolbar() to include session selector if multiple sessions exist in src/Core/Debugger.php

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently - debug history persists across browser sessions

---

## Phase 5: User Story 3 - Unique Request Identification (Priority: P3)

**Goal**: Provide unique UUID per request for precise tracking and correlation across debug data and logs

**Independent Test**: Make concurrent requests, verify each has unique UUID and data is correctly isolated

### Implementation for User Story 3

- [ ] T034 [US3] Verify generateUUID() follows RFC 4122 v4 specification (version bits, variant bits) in src/Core/Debugger.php
- [ ] T035 [US3] Add getRequestUUID() method in src/Core/Debugger.php to expose current request UUID
- [ ] T036 [US3] Store UUID in $this->request object as new property $uuid in src/Core/Debugger.php
- [ ] T037 [US3] Update DebuggerRequest class to include public string $uuid property in src/Core/Debugger.php
- [ ] T038 [US3] Modify end() method to store UUID before writing to filesystem in src/Core/Debugger.php
- [ ] T039 [US3] Add UUID to debug toolbar display in toolbar() method in src/Core/Debugger.php
- [ ] T040 [US3] Implement findRequestByUUID() method in src/Core/Debugger.php to locate request file by UUID
- [ ] T041 [US3] Update toolbar() to support UUID-based request lookup in src/Core/Debugger.php

**Checkpoint**: All user stories should now be independently functional - unique UUIDs enable precise request tracking

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories and ensure production quality

- [ ] T042 [P] Add comprehensive PHPDoc comments to all new methods in src/Core/Debugger.php
- [ ] T043 [P] Verify all methods have proper type declarations (parameters and return types) in src/Core/Debugger.php
- [X] T044 [P] Run PHPStan analysis (level max) and fix any errors in src/Core/Debugger.php
- [X] T045 [P] Test with PHPUnit and verify existing tests still pass in tests/Core/DebuggerTest.php
- [ ] T046 Add unit tests for generateUUID() (format validation, uniqueness) in tests/Core/DebuggerTest.php
- [ ] T047 Add unit tests for getBrowserSessionId() (cookie generation, validation) in tests/Core/DebuggerTest.php
- [ ] T048 Add unit tests for atomicWrite() (write-then-rename, error handling) in tests/Core/DebuggerTest.php
- [ ] T049 Add integration test for concurrent request handling (file locking) in tests/Core/DebuggerTest.php
- [ ] T050 Add integration test for history limit enforcement in tests/Core/DebuggerTest.php
- [ ] T051 Add integration test for filesystem error graceful degradation in tests/Core/DebuggerTest.php
- [ ] T052 Add integration test for session-independent operation (no Session::start()) in tests/Core/DebuggerTest.php
- [ ] T053 [P] Update quickstart.md with any implementation deviations or additional details
- [ ] T054 [P] Verify JSON output matches contracts/debug-request.schema.json specification
- [ ] T055 [P] Verify history file matches contracts/debug-history.schema.json specification
- [ ] T056 Test cleanup: Remove $__sessionKey static property if no longer used in src/Core/Debugger.php
- [ ] T057 Test cleanup: Update constructor parameter list to remove sessionKey if deprecated in src/Core/Debugger.php
- [X] T058 Run complete test suite (bash test.sh) and verify 90%+ coverage maintained
- [ ] T059 Final validation: Run all quickstart.md test scenarios manually
- [X] T060 Final validation: Verify all 7 success criteria from spec.md are met

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3+)**: All depend on Foundational phase completion
  - User stories can then proceed in parallel (if staffed)
  - Or sequentially in priority order (P1 → P2 → P3)
- **Polish (Phase 6)**: Depends on all desired user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - No dependencies on other stories
  - **CRITICAL**: This is the MVP - all core functionality must work
  - Makes debugger functional with filesystem storage
  - Eliminates session pollution
  - Enables session-independent operation
  
- **User Story 2 (P2)**: Can start after Foundational (Phase 2) - Builds on US1 infrastructure
  - Adds persistent history across browser sessions
  - Requires cookie-based session tracking from US1
  - Independently testable but integrates with US1
  
- **User Story 3 (P3)**: Can start after Foundational (Phase 2) - Enhances US1 & US2
  - Adds UUID exposure and lookup capabilities
  - Requires UUID generation from foundational phase
  - Pure enhancement - can be implemented last or skipped

### Within Each User Story

- **Setup Phase**: All utility methods can be implemented in parallel (marked [P])
- **Foundational Phase**: 
  - T007-T009 (write operations) can be done in parallel
  - T010-T011 (read/write history) can be done in parallel
  - T012 depends on T010-T011 (updateHistory needs read/write methods)
  - T013 is independent
  
- **User Story 1**:
  - T014-T017 modify end() method (sequential edits to same method)
  - T018 modifies constructor/initialization
  - T019-T022 modify toolbar() method (sequential edits to same method)
  - T023-T024 clean up session-related code
  - T025 modifies constructor
  - T026 regenerates facade (must be LAST in this phase)
  
- **User Story 2**:
  - T027-T030 verify/ensure existing implementation (can be done in parallel if reviewing different methods)
  - T031-T033 enhance toolbar for multi-session support
  
- **User Story 3**:
  - T034-T035 verify/add UUID methods
  - T036-T038 add UUID to request object
  - T039-T041 expose UUID in toolbar

### Parallel Opportunities

- **Phase 1 (Setup)**: T003, T004, T005, T006 can all run in parallel (different utility methods)
- **Phase 2 (Foundational)**: 
  - T007, T008, T009 in parallel
  - T010, T011, T013 in parallel
  - T012 after T010-T011 complete
  
- **Phase 3 (US1)**: Limited parallelization due to editing same methods (end, toolbar)
- **Phase 4 (US2)**: T027-T030 verification tasks can run in parallel
- **Phase 5 (US3)**: T034-T037 can run in parallel (different aspects)
- **Phase 6 (Polish)**: T042, T043, T044, T045, T053, T054, T055 can all run in parallel (different files or independent checks)

---

## Parallel Example: Setup Phase

```bash
# All setup utility methods can be implemented simultaneously
# by different developers or in separate commits

# Developer A: UUID generation
vim src/Core/Debugger.php  # Implement generateUUID()

# Developer B: Session tracking  
vim src/Core/Debugger.php  # Implement getBrowserSessionId()

# Developer C: Path utilities
vim src/Core/Debugger.php  # Implement getStoragePath() and ensureDirectory()
```

## Parallel Example: Foundational Phase

```bash
# Write operations
vim src/Core/Debugger.php  # T007: atomicWrite()
vim src/Core/Debugger.php  # T008: getRequestPath()
vim src/Core/Debugger.php  # T009: getHistoryPath()

# History operations (after above complete)
vim src/Core/Debugger.php  # T010: readHistory()
vim src/Core/Debugger.php  # T011: writeHistory()
vim src/Core/Debugger.php  # T013: deleteRequestFile()

# Then update history (depends on read/write)
vim src/Core/Debugger.php  # T012: updateHistory()
```

---

## Implementation Strategy

### MVP Definition (Phase 3 - User Story 1 Only)

The **Minimum Viable Product** consists of:
- ✅ Setup phase (T001-T006): Storage foundation
- ✅ Foundational phase (T007-T013): Filesystem operations
- ✅ User Story 1 (T014-T026): Core functionality

This delivers the primary value:
1. Eliminates session pollution (no 'debugger' key in $_SESSION)
2. Removes PHP session dependency (works without Session::start())
3. Stores debug data on filesystem
4. Maintains history per browser session
5. Graceful error handling

**Stop Here for MVP**: After T026, you have a fully functional debugger with filesystem storage. User Stories 2 and 3 are enhancements.

### Incremental Delivery

- **Sprint 1**: Setup + Foundational + US1 (T001-T026) = MVP
  - Estimated effort: 8-12 hours
  - Deliverable: Working filesystem-based debugger
  
- **Sprint 2**: US2 (T027-T033) = Persistent History Enhancement
  - Estimated effort: 3-4 hours
  - Deliverable: Cross-session debug history access
  
- **Sprint 3**: US3 (T034-T041) = UUID Tracking Enhancement
  - Estimated effort: 2-3 hours
  - Deliverable: UUID exposure and lookup
  
- **Sprint 4**: Polish (T042-T060) = Production Quality
  - Estimated effort: 6-8 hours
  - Deliverable: Tests, documentation, validation

### Testing Strategy

**During Development**:
- Run `bash test.sh` after each major phase
- Verify PHPStan passes: `vendor/bin/phpstan analyze`
- Check existing tests: `vendor/bin/phpunit tests/Core/DebuggerTest.php`

**After US1 Complete**:
- Manual testing: Enable debugger, make requests, inspect session (no 'debugger' key)
- Manual testing: Disable Session::start(), verify debugger still works
- Check filesystem: Verify JSON files created in storage path

**After US2 Complete**:
- Manual testing: Make requests, close browser, open new session, verify history accessible
- Check filesystem: Verify session-based directory structure

**After US3 Complete**:
- Manual testing: Make concurrent requests, verify unique UUIDs
- Use UUID to locate specific request file

**Before Merge**:
- All 60 tasks complete
- PHPStan level max passes
- PHPUnit test coverage ≥ 90%
- All 7 success criteria validated
- Quickstart scenarios tested

---

## Task Count Summary

- **Phase 1 (Setup)**: 6 tasks
- **Phase 2 (Foundational)**: 7 tasks (BLOCKING)
- **Phase 3 (US1 - MVP)**: 13 tasks
- **Phase 4 (US2)**: 7 tasks
- **Phase 5 (US3)**: 8 tasks
- **Phase 6 (Polish)**: 19 tasks

**Total**: 60 tasks

**MVP**: 26 tasks (Setup + Foundational + US1)
**Parallel opportunities**: ~15 tasks marked [P]
**Independent stories**: 3 (US1, US2, US3)
