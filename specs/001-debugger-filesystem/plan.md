# Implementation Plan: Debugger Filesystem Storage

**Branch**: `001-debugger-filesystem` | **Date**: 2025-12-01 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/001-debugger-filesystem/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

Refactor the Debugger class to store request debugging data on the filesystem instead of in `$_SESSION`. Each request gets a unique UUID and is stored in an individual JSON file. History tracking per browser session is maintained via a session-based history file containing an ordered list of UUIDs. This change eliminates session pollution (debugger data appearing in session inspection), removes the PHP session dependency (debugger works without `Session::start()`), enables persistent debug history across browser sessions, and prevents debug data from inflating session size.

## Technical Context

**Language/Version**: PHP 8.0+ (using constructor property promotion, type declarations, null-safe operator)  
**Primary Dependencies**: None beyond PHP standard library (random_bytes for UUID generation, file I/O functions)  
**Storage**: Filesystem-based JSON storage for debug request data and history files  
**Testing**: PHPUnit with 90%+ line coverage requirement, integration tests for filesystem operations  
**Target Platform**: Development environments (Linux/macOS/Windows) with writable filesystem  
**Project Type**: Single library project (mintyphp/core framework)  
**Performance Goals**: <1ms overhead for filesystem writes, no impact on application performance when debugger disabled  
**Constraints**: Filesystem must be writable, storage operations must not crash application on failure, graceful degradation required  
**Scale/Scope**: 16 core classes total, modifying 1 class (Debugger), ~500 lines affected, maintaining existing public API

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

**Phase 0 Complete** ✅ - Re-evaluation after research

**Phase 1 Complete** ✅ - Final re-evaluation after design:

- [x] **Simplicity First**: ✅ Design maintains simplicity. Storage structure is straightforward (UUID-based files, JSON history). No complex abstractions or patterns. Browser session tracking via simple cookie mechanism.
- [x] **Security by Design**: ✅ JSON schemas validate data structure. HttpOnly cookies prevent XSS. UUID-based filenames prevent path traversal. No user input in storage paths. Graceful error handling prevents information leakage.
- [x] **Explicit Over Magic**: ✅ Data model explicitly documents all entities. Storage paths are clear and predictable. File formats are human-readable JSON. No hidden conventions or implicit behaviors.
- [x] **Single Variable Scope**: ✅ No change to MVC variable scope - debugger remains infrastructure separate from application logic.
- [x] **Native PHP Patterns**: ✅ Uses json_encode/json_decode, file I/O functions, flock, setcookie, $_COOKIE. All operations use native PHP without abstractions or libraries.
- [x] **Debuggability**: ✅ Significantly improved. JSON files are human-readable. File structure is browsable. Error logging provides visibility. Data persists for investigation.
- [x] **Static Analysis Compliance**: ✅ JSON schemas define data contracts. All types properly declared. Bool returns for error handling. No mixed types or unsafe operations.
- [x] **Comprehensive Test Coverage**: ✅ Test strategy defined in data-model.md. Unit tests for all operations. Integration tests for concurrent access. Error conditions testable. 90%+ coverage achievable.
- [x] **Request Lifecycle Separation**: ✅ Debugger writes to filesystem in end() (after response). No impact on routing/execution/rendering. Storage operations are post-response infrastructure.

**Constitution Compliance**: ✅ ALL 9 PRINCIPLES PASSED

Ready for Phase 2 (Implementation Tasks).

## Project Structure

### Documentation (this feature)

```text
specs/001-debugger-filesystem/
├── spec.md              # Feature specification (completed)
├── plan.md              # This file (implementation plan)
├── research.md          # Phase 0: Technical decisions and patterns
├── data-model.md        # Phase 1: Storage format and file structure
├── quickstart.md        # Phase 1: Developer guide for filesystem storage
├── contracts/           # Phase 1: JSON schemas for storage format
└── checklists/
    └── requirements.md  # Specification quality checklist (completed)
```

### Source Code (repository root)

```text
src/
├── Core/
│   └── Debugger.php        # MODIFY: Replace $_SESSION storage with filesystem
└── Debugger.php            # AUTO-GENERATED: Facade (regenerate after Core changes)

tests/
└── Core/
    └── DebuggerTest.php    # MODIFY: Add filesystem storage tests

.specify/
├── memory/
│   └── constitution.md     # Framework governance (reference only)
└── templates/
    └── plan-template.md    # This template
```

**Structure Decision**: Single project structure. This is a modification to existing core framework class (`src/Core/Debugger.php`). No new classes or modules required. The facade wrapper (`src/Debugger.php`) will be auto-regenerated via `generate_wrappers.php` after core changes are complete. Test file (`tests/Core/DebuggerTest.php`) will be extended with new filesystem storage test cases.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

No constitution violations. All principles upheld:
- Simplifies by removing session dependency
- Uses native PHP patterns (file I/O, JSON)
- Explicit configuration via static properties
- Maintains test coverage requirements
- No impact on MVC architecture or request lifecycle

---

## Phase 0: Research & Technical Decisions

**Objective**: Resolve all technical unknowns and document best practices for filesystem-based debug storage.

### Research Tasks

1. **Browser Session Tracking Without PHP Sessions**
   - Research: Cookie-based session identifier strategies independent of PHP sessions
   - Decision needed: How to generate and persist browser session identifier
   - Options: HTTP-only cookie, client-generated UUID in header, server-generated token
   - Best practice: Security implications, collision avoidance

2. **UUID Generation in PHP**
   - Research: RFC 4122 UUID v4 implementation using random_bytes()
   - Decision needed: Format function for converting bytes to UUID string
   - Options: Manual bit manipulation vs library, performance considerations
   - Best practice: Entropy quality, collision probability at scale

3. **Atomic File Operations**
   - Research: Write-then-rename pattern for atomic writes in PHP
   - Decision needed: Temp file naming convention, error handling on rename failure
   - Options: file_put_contents with LOCK_EX, write to .tmp then rename()
   - Best practice: Race condition prevention, partial write protection

4. **Concurrent History File Access**
   - Research: File locking strategies (flock) for concurrent request handling
   - Decision needed: Lock duration, exclusive vs shared locks, timeout handling
   - Options: flock(LOCK_EX), flock(LOCK_SH), lock-free data structures
   - Best practice: Deadlock prevention, performance under concurrent load

5. **Storage Path Configuration**
   - Research: Default storage location for debug data (sys_get_temp_dir, project-relative)
   - Decision needed: Default path, directory creation strategy, permission requirements
   - Options: /tmp/mintyphp-debug, .cache/debug, configurable via static property
   - Best practice: Cross-platform compatibility (Linux/Mac/Windows), cleanup policies

6. **Graceful Filesystem Failure Handling**
   - Research: Error handling patterns for file I/O failures
   - Decision needed: Return null, log error, throw exception, or silent degradation
   - Options: Try-catch with logging, @-suppression with error check, result objects
   - Best practice: PHPStan compatibility, debugger reliability principles

**Output**: `research.md` documenting decisions with rationale for each unknown

---

## Phase 1: Data Model & Contracts

**Prerequisites**: Phase 0 research complete

**Objective**: Design filesystem storage structure and JSON schemas for request data and history files.

### Data Model (`data-model.md`)

Document the following entities and their filesystem representation:

1. **Debug Request File**
   - Entity: Individual request debug data
   - Filename pattern: `{uuid}.json` where uuid is RFC 4122 v4
   - Location: `{storage_path}/{session_id}/{uuid}.json`
   - Content: JSON-serialized DebuggerRequest object
   - Lifecycle: Created on request end, deleted when history limit exceeded

2. **Debug History File**
   - Entity: Ordered list of request UUIDs per browser session
   - Filename pattern: `session-{session_id}.json`
   - Location: `{storage_path}/session-{session_id}.json`
   - Content: JSON array of UUID strings, most recent first
   - Lifecycle: Updated on each request, persists across browser sessions

3. **Browser Session Identifier**
   - Entity: Unique identifier for browser session (independent of PHP session)
   - Storage: HTTP-only cookie (name: `mintyphp_debug_session`)
   - Format: UUID v4 string
   - Lifecycle: Set on first request with debugger enabled, persists until cookie expires

4. **Storage Directory Structure**
   ```
   {storage_path}/
   └── debugger/
       ├── session-{id1}.json      # History: ["uuid1", "uuid2", ...]
       ├── {id1}/                  # Session directory
       │   ├── {uuid1}.json        # Request data
       │   └── {uuid2}.json
       ├── session-{id2}.json
       └── {id2}/
           └── {uuid3}.json
   ```

### API Contracts (`contracts/`)

Generate JSON schemas for storage formats:

1. **`contracts/debug-request.schema.json`**
   - Schema for DebuggerRequest serialization
   - Includes all nested objects (DebuggerQuery, DebuggerApiCall, DebuggerRoute, etc.)
   - Used for validation and documentation

2. **`contracts/debug-history.schema.json`**
   - Schema for history file format
   - Simple array of UUID strings with max length constraint

### Developer Guide (`quickstart.md`)

Document for developers working on this feature:

```markdown
# Debugger Filesystem Storage - Developer Guide

## Overview
The Debugger class stores request data on filesystem instead of $_SESSION.

## Key Changes
1. New static property: `Debugger::$__storagePath` (default: sys_get_temp_dir() . '/mintyphp-debug')
2. Removed dependency on PHP sessions - uses cookie-based browser session tracking
3. UUID generation for unique request identification
4. Atomic file writes with flock for concurrent safety

## Storage Structure
- Request files: `{storagePath}/{sessionId}/{uuid}.json`
- History files: `{storagePath}/session-{sessionId}.json`

## Modified Methods
- `end()`: Writes to filesystem instead of $_SESSION
- `toolbar()`: Reads from filesystem instead of $_SESSION
- NEW `generateUUID()`: Creates RFC 4122 v4 UUIDs
- NEW `getSessionId()`: Gets/creates browser session identifier
- NEW `writeRequest()`: Atomic file write for request data
- NEW `updateHistory()`: Manages history file with locking

## Testing
- Unit tests: UUID generation, file operations, error handling
- Integration tests: Concurrent requests, filesystem failures, history limits
- Coverage target: 90%+ maintained

## Migration Impact
- BREAKING: $_SESSION[$sessionKey] no longer used
- Applications reading debugger data from session must update to filesystem
- Debugger toolbar HTML unchanged (internal implementation only)
```

### Agent Context Update

Run agent context update script:
```bash
bash .specify/scripts/bash/update-agent-context.sh copilot
```

This updates `.github/copilot-instructions.md` with new technologies/patterns from this plan.

**Output**: `data-model.md`, `contracts/*.schema.json`, `quickstart.md`, updated agent context

---

## Phase 2: Implementation Tasks

**Phase 2 Complete** ✅

**Prerequisites**: Phase 1 design complete, Constitution re-check passed

**Objective**: Break down implementation into atomic, testable tasks for `/speckit.tasks` command.

### Task Categories

This phase is completed by the `/speckit.tasks` command, which generates `tasks.md` with specific implementation steps including:

1. **Storage Infrastructure**
   - Implement UUID generation method
   - Implement browser session tracking (cookie-based)
   - Implement storage directory creation with error handling
   - Add configuration property for storage path

2. **File Operations**
   - Implement atomic request file writing
   - Implement history file reading with locking
   - Implement history file writing with locking
   - Implement history limit enforcement with file deletion

3. **Core Debugger Changes**
   - Modify `end()` method to use filesystem storage
   - Modify `toolbar()` method to read from filesystem
   - Remove $_SESSION dependency
   - Add graceful filesystem error handling

4. **Testing**
   - Unit tests for UUID generation
   - Unit tests for atomic file writes
   - Unit tests for history management
   - Integration tests for concurrent access
   - Integration tests for filesystem failures
   - Coverage verification (maintain 90%+)

5. **Documentation & Cleanup**
   - Update PHPDoc comments for modified methods
   - Regenerate facade wrapper (`generate_wrappers.php`)
   - Run PHPStan level max validation
   - Update README if debugger usage changes

**Output**: Detailed task breakdown in `tasks.md` (generated by `/speckit.tasks` command)

---

## Success Criteria Checklist

Verify all success criteria from spec are met:

- [ ] **SC-001**: Session inspection shows no 'debugger' key (test: inspect $_SESSION dump)
- [ ] **SC-002**: Request data persists after session ends (test: close browser, reopen, check filesystem)
- [ ] **SC-003**: Unique UUIDs with no collisions (test: generate 10,000+ UUIDs, verify uniqueness)
- [ ] **SC-004**: Graceful degradation on filesystem errors (test: read-only directory, verify no crash)
- [ ] **SC-005**: History limit respected (test: make 15 requests with limit=10, verify 10 files)
- [ ] **SC-006**: No history corruption from concurrent requests (test: parallel requests, verify history integrity)
- [ ] **SC-007**: Works without PHP sessions (test: debugger enabled, no Session::start(), verify data capture)

---

## Notes

- **Backward Compatibility**: This is a BREAKING change for applications that read `$_SESSION[$debuggerKey]`. Migration path: read from filesystem using storage path.
- **Performance**: Filesystem I/O adds ~1ms overhead per request. Acceptable for development environment where debugger is used.
- **Cleanup**: Out of scope for this feature. Separate garbage collection feature will handle old debug data removal.
- **Multi-Server**: Out of scope. Single-server filesystem storage only. Shared storage backends (NFS, S3) are future enhancements.
