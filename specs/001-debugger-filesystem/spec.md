# Feature Specification: Refactor Debugger to Filesystem Storage

**Feature Branch**: `001-debugger-filesystem`  
**Created**: 2025-12-01  
**Status**: Draft  
**Input**: User description: "Debugger currently uses a key in the $_SESSION global variable that it also debugs and this is not ideal. Store the request with there unique request id (uuid?) on the filesystem instead. Also store a history file with uuid list in a file named based on the session id."

## User Scenarios & Testing *(mandatory)*

<!--
  IMPORTANT: User stories should be PRIORITIZED as user journeys ordered by importance.
  Each user story/journey must be INDEPENDENTLY TESTABLE - meaning if you implement just ONE of them,
  you should still have a viable MVP (Minimum Viable Product) that delivers value.
  
  Assign priorities (P1, P2, P3, etc.) to each story, where P1 is the most critical.
  Think of each story as a standalone slice of functionality that can be:
  - Developed independently
  - Tested independently
  - Deployed independently
  - Demonstrated to users independently
-->

### User Story 1 - Debug Request Inspection Without Session Pollution (Priority: P1)

A developer enables debugging mode and inspects request data in the debugger toolbar without the debugger's own storage affecting the session data being debugged. The debugger functions independently of whether sessions are enabled.

**Why this priority**: Core functional issue - debugger currently stores data in `$_SESSION['debugger']` which (1) appears in session debug output creating confusion, (2) requires sessions to be enabled even when application doesn't use sessions, and (3) pollutes the data being inspected.

**Independent Test**: Enable debugger without starting session, make a request, verify debugger captures and displays request data correctly.

**Acceptance Scenarios**:

1. **Given** debugger is enabled and session is active, **When** developer inspects session data in debugger toolbar, **Then** no 'debugger' key appears in session output
2. **Given** debugger is enabled and session is NOT started, **When** developer makes a request, **Then** debugger still captures and displays request data
3. **Given** debugger is enabled with history=10, **When** developer makes 15 requests, **Then** only 10 most recent request files exist on filesystem
4. **Given** debugger stores request data to filesystem, **When** filesystem write fails, **Then** debugger gracefully degrades without crashing application

---

### User Story 2 - Persistent Debug History Across Requests (Priority: P2)

A developer can review request history from previous browser sessions by accessing debug data stored on filesystem, enabling investigation of issues that occurred earlier. History is tracked per browser session using a session identifier (independent of PHP session management).

**Why this priority**: Improves debugging workflow by preserving history beyond current browser session lifetime and works regardless of whether application uses PHP sessions.

**Independent Test**: Make requests in browser session A, close browser and start new browser session B, verify session A's debug history is still accessible via filesystem.

**Acceptance Scenarios**:

1. **Given** debugger has stored 5 requests in browser session A, **When** browser is closed and new browser session B starts, **Then** session A's request history remains accessible on filesystem
2. **Given** multiple browser sessions have debug data, **When** developer accesses debugger interface, **Then** can select and view history from any browser session
3. **Given** debug data files exist for browser session X, **When** session X is no longer needed, **Then** corresponding debug files can be cleaned up
4. **Given** application doesn't use PHP sessions, **When** debugger is enabled, **Then** debugger still tracks request history using its own session identifier

---

### User Story 3 - Unique Request Identification (Priority: P3)

Each request has a unique identifier (UUID) enabling precise tracking and correlation across debug data, logs, and error reports.

**Why this priority**: Improves debugging precision and enables correlation with external logging systems.

**Independent Test**: Make concurrent requests, verify each has unique UUID and data is correctly isolated.

**Acceptance Scenarios**:

1. **Given** two requests execute concurrently, **When** both are debugged, **Then** each has a unique UUID and isolated data file
2. **Given** a request UUID is known, **When** developer searches filesystem, **Then** can directly locate corresponding debug data file
3. **Given** request fails with error, **When** error is logged with UUID, **Then** developer can correlate error with debug data via UUID

---

### Edge Cases

- What happens when filesystem storage directory doesn't exist or isn't writable?
- How does system handle concurrent writes to history file from multiple requests?
- What happens when debug storage exceeds disk space limits?
- How are orphaned debug files cleaned up (requests that didn't complete)?
- What happens if UUID generation fails or produces collisions?
- How does debugger track browser sessions when PHP sessions are not enabled?
- What happens when application disables sessions but debugger needs session tracking?

## Requirements *(mandatory)*

<!--
  ACTION REQUIRED: The content in this section represents placeholders.
  Fill them out with the right functional requirements.
-->

### Functional Requirements

- **FR-001**: Debugger MUST store request data to filesystem instead of $_SESSION
- **FR-002**: Each request MUST be assigned a unique UUID identifier
- **FR-003**: Request data MUST be stored in individual files named by UUID (e.g., `{uuid}.json`)
- **FR-004**: History file MUST be stored per browser session, named by session identifier independent of PHP session system (e.g., `session-{identifier}.json`)
- **FR-005**: History file MUST contain ordered list of UUIDs (most recent first)
- **FR-006**: History list MUST respect configured history limit (default 10 requests)
- **FR-007**: When history limit exceeded, oldest request files MUST be deleted from filesystem
- **FR-008**: Debugger MUST gracefully handle filesystem errors without crashing application
- **FR-009**: Debug storage directory path MUST be configurable via static property
- **FR-010**: Session data inspection MUST NOT show debugger's internal storage keys
- **FR-011**: Debugger MUST use proper file locking to prevent race conditions during history updates
- **FR-012**: Request data files MUST be written atomically (write to temp, then rename)
- **FR-013**: Debugger MUST function independently of PHP session state (works with or without Session::start())

### Key Entities

- **Debug Request**: Individual request debugging data (queries, timing, route, session snapshot, etc.)
  - Attributes: UUID (unique identifier), timestamp, user, type, duration, memory, status, route, queries, API calls, cache calls, session states, classes loaded
  - Storage: Individual JSON file per request, named `{uuid}.json`

- **Debug History**: Ordered list of request UUIDs for a browser session
  - Attributes: Session identifier (filename basis, independent of PHP sessions), UUID list (ordered, most recent first), max size (configurable)
  - Storage: Single JSON file per browser session, named `session-{identifier}.json`

- **Storage Directory**: Filesystem location for debug data
  - Path structure: `{storage_path}/{identifier}/` contains request JSON files
  - History file: `{storage_path}/session-{identifier}.json` contains UUID list

## Success Criteria *(mandatory)*

<!--
  ACTION REQUIRED: Define measurable success criteria.
  These must be technology-agnostic and measurable.
-->

### Measurable Outcomes

- **SC-001**: Session inspection in debugger shows no 'debugger' key or internal storage artifacts
- **SC-002**: Request data persists on filesystem and remains accessible after session ends
- **SC-003**: Each request has unique UUID with no collisions across 10,000+ requests
- **SC-004**: Debugger degrades gracefully when filesystem operations fail (returns empty data, logs error)
- **SC-005**: History limit is respected - filesystem contains max N request files per session (N = configured limit)
- **SC-006**: Concurrent requests do not corrupt history file (proper file locking prevents race conditions)
- **SC-007**: Debugger captures and displays request data correctly whether PHP sessions are enabled or disabled

## Assumptions

- Debugger is only enabled in development environments with sufficient disk space
- Browser session can be tracked using cookies or similar mechanism independent of PHP sessions
- Filesystem has write permissions for debug storage directory
- UUID collisions are astronomically unlikely with proper UUID v4 implementation
- Debug data does not need to survive server restarts in long term (ephemeral debugging data)
- Garbage collection of old debug data is handled separately (not part of this feature)

## Dependencies

- **PHP Extensions**: None beyond standard library (UUID generation via random_bytes)
- **Browser Session Tracking**: Requires mechanism to track browser sessions (cookies, headers) independent of PHP session system
- **Filesystem Access**: Requires writable directory for debug storage

## Out of Scope

- Web interface for browsing debug history (existing debugger toolbar remains unchanged)
- Automatic cleanup/garbage collection of old debug data (separate feature)
- Compression of debug data files
- Remote storage backends (S3, database, etc.)
- Multi-server debug data aggregation
- Real-time debug data streaming

## Technical Notes

### Current Implementation Context

Current Debugger stores data in PHP session storage which creates problems:
1. Session inspection shows debugger's internal data in output
2. Debug history inflates session size
3. Data lost when PHP session expires
4. Cannot review history across browser sessions
5. Forces applications to enable PHP sessions even when they don't use them

### Storage Requirements

Debug data storage must support:
- Individual request data files with unique identifiers
- Browser session tracking independent of PHP session system
- History tracking per browser session (ordered list of request identifiers)
- Configurable storage location
- Automatic cleanup when history limit exceeded
- Graceful degradation on storage failures

### Data Integrity Requirements

- Request files must be written atomically to prevent corruption
- History updates must be protected against concurrent modifications
- Storage operations must not crash application if they fail
- Each request must have globally unique identifier
- Browser session tracking must work independently of PHP session state
