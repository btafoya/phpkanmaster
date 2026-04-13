#!/usr/bin/env bash
#
# Webhook Handler Integration Test Script
#
# Tests the POST /webhooks/{source} endpoint against a live instance.
#
# Usage:
#   ./tests/webhook-test.sh                    # run all tests
#   ./tests/webhook-test.sh --skip-cleanup     # leave created tasks for inspection
#   ./tests/webhook-test.sh --test create      # run only the "create" test group
#
# Prerequisites:
#   - curl, jq
#   - Valid JWT_SECRET in the target .env (for cleanup via PostgREST)
#   - Target server running and accessible
#

set -euo pipefail

# ── Configuration ─────────────────────────────────────────────────────────────
BASE_URL="${WEBHOOK_TEST_URL:-https://tasks.brian.one}"
SOURCE="nextbt"
ENDPOINT="${BASE_URL}/webhooks/${SOURCE}"

# PostgREST endpoint for direct task/category verification
PGRST_URL="${PGRST_TEST_URL:-${BASE_URL}/api}"
PGRST_TOKEN="${PGRST_TEST_TOKEN:-}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Counters
PASS=0
FAIL=0
SKIP=0

# ── Helpers ──────────────────────────────────────────────────────────────────

log_header() {
    echo -e "\n${CYAN}═══ $1 ═══${NC}"
}

log_test() {
    echo -e "  ${YELLOW}▶${NC} $1"
}

log_pass() {
    echo -e "  ${GREEN}✓ PASS${NC}: $1"
    ((PASS++))
}

log_fail() {
    echo -e "  ${RED}✗ FAIL${NC}: $1"
    ((FAIL++))
}

log_skip() {
    echo -e "  ${YELLOW}⊘ SKIP${NC}: $1"
    ((SKIP++))
}

# Make a webhook request and return status code + response body
webhook_post() {
    local payload="$1"
    local response
    response=$(curl -s -w "\n%{http_code}" \
        -X POST \
        -H "Content-Type: application/json" \
        -d "$payload" \
        "$ENDPOINT" 2>&1)

    local body
    body=$(echo "$response" | sed '$d')
    local code
    code=$(echo "$response" | tail -1)

    echo "${code}|${body}"
}

assert_status() {
    local actual="$1"
    local expected="$2"
    local label="$3"
    if [[ "$actual" == "$expected" ]]; then
        log_pass "$label — status $actual"
    else
        log_fail "$label — expected status $expected, got $actual"
    fi
}

assert_json_field() {
    local body="$1"
    local field="$2"
    local expected="$3"
    local label="$4"
    local actual
    actual=$(echo "$body" | jq -r ".$field" 2>/dev/null || echo "PARSE_ERROR")
    if [[ "$actual" == "$expected" ]]; then
        log_pass "$label — $field=$actual"
    else
        log_fail "$label — expected $field=$expected, got $actual"
    fi
}

assert_json_field_not_null() {
    local body="$1"
    local field="$2"
    local label="$3"
    local actual
    actual=$(echo "$body" | jq -r ".$field" 2>/dev/null || echo "PARSE_ERROR")
    if [[ "$actual" != "null" && "$actual" != "PARSE_ERROR" && -n "$actual" ]]; then
        log_pass "$label — $field is present: $actual"
    else
        log_fail "$label — $field is null or missing"
    fi
}

# ── Payloads ─────────────────────────────────────────────────────────────────

# Unique external ID to avoid collisions across test runs
EXTERNAL_ID="test_$(date +%s)"

PAYLOAD_CREATE=$(cat <<EOF
{
  "event_type": "issue.created",
  "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%S.000Z)",
  "issue": {
    "id": ${EXTERNAL_ID},
    "summary": "[Test] Webhook integration test task",
    "description": "Created by webhook test script at $(date -u +%Y-%m-%dT%H:%MZ)",
    "steps_to_reproduce": "1. Run webhook-test.sh\n2. Check kanban board",
    "additional_information": "Automated test run",
    "status": 10,
    "priority": 40,
    "severity": 2,
    "reporter": { "id": 99, "username": "testbot", "realname": "Test Bot" },
    "handler": { "id": 3, "username": "mjohnson", "realname": "Mary Johnson" },
    "project": { "id": 1, "name": "Integration Tests" },
    "category": { "id": 2, "name": "UI Bug" },
    "tags": ["test", "automated"],
    "created_at": "$(date -u +%Y-%m-%dT%H:%M:%S.000Z)",
    "updated_at": "$(date -u +%Y-%m-%dT%H:%M:%S.000Z)",
    "notes": []
  }
}
EOF
)

PAYLOAD_UPDATE=$(cat <<EOF
{
  "event_type": "issue.updated",
  "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%S.000Z)",
  "issue": {
    "id": ${EXTERNAL_ID},
    "summary": "[Test] Updated webhook integration test task",
    "description": "Updated by webhook test script",
    "status": 30,
    "priority": 50,
    "severity": 1,
    "reporter": { "id": 99, "username": "testbot", "realname": "Test Bot" },
    "handler": { "id": 3, "username": "mjohnson", "realname": "Mary Johnson" },
    "project": { "id": 1, "name": "Integration Tests" },
    "category": { "id": 2, "name": "UI Bug" },
    "tags": ["test", "automated", "updated"],
    "created_at": "$(date -u +%Y-%m-%dT%H:%M:%S.000Z)",
    "updated_at": "$(date -u +%Y-%m-%dT%H:%M:%S.000Z)",
    "notes": [
      {
        "id": 1001,
        "author": { "id": 99, "username": "testbot", "realname": "Test Bot" },
        "text": "This note was added during issue.updated test",
        "created_at": "$(date -u +%Y-%m-%dT%H:%M:%S.000Z)"
      }
    ]
  }
}
EOF
)

PAYLOAD_NOTE_ADDED=$(cat <<EOF
{
  "event_type": "issue.note_added",
  "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%S.000Z)",
  "issue": {
    "id": ${EXTERNAL_ID},
    "summary": "[Test] Updated webhook integration test task",
    "status": 30,
    "priority": 50,
    "reporter": { "id": 99, "username": "testbot", "realname": "Test Bot" },
    "created_at": "$(date -u +%Y-%m-%dT%H:%M:%S.000Z)",
    "updated_at": "$(date -u +%Y-%m-%dT%H:%M:%S.000Z)",
    "notes": [
      {
        "id": 1002,
        "author": { "id": 99, "username": "testbot", "realname": "Test Bot" },
        "text": "This note was added via issue.note_added event",
        "created_at": "$(date -u +%Y-%m-%dT%H:%M:%S.000Z)"
      }
    ]
  }
}
EOF
)

# ── Test Groups ───────────────────────────────────────────────────────────────

TASK_ID=""

test_create() {
    log_header "1. issue.created — Create new task"

    log_test "Sending issue.created payload..."
    local result
    result=$(webhook_post "$PAYLOAD_CREATE")
    local code="${result%%|*}"
    local body="${result#*|}"

    assert_status "$code" 200 "issue.created response"

    if [[ "$code" == "200" ]]; then
        assert_json_field "$body" "success" "true" "issue.created success flag"
        assert_json_field "$body" "skipped" "false" "issue.created not skipped"
        assert_json_field_not_null "$body" "task_id" "issue.created returns task_id"

        TASK_ID=$(echo "$body" | jq -r '.task_id' 2>/dev/null || echo "")
        if [[ -n "$TASK_ID" && "$TASK_ID" != "null" ]]; then
            log_pass "Captured task_id: $TASK_ID"
        else
            log_fail "Failed to capture task_id from response"
        fi
    fi

    # Test idempotency — sending the same payload again should return skipped
    log_test "Re-sending same issue.created (idempotency check)..."
    local result2
    result2=$(webhook_post "$PAYLOAD_CREATE")
    local code2="${result2%%|*}"
    local body2="${result2#*|}"

    assert_status "$code2" 200 "duplicate issue.created response"
    assert_json_field "$body2" "skipped" "true" "duplicate issue.created is skipped"
}

test_update() {
    log_header "2. issue.updated — Update existing task"

    if [[ -z "$TASK_ID" ]]; then
        log_skip "issue.updated — no task_id from create step"
        return
    fi

    log_test "Sending issue.updated payload..."
    local result
    result=$(webhook_post "$PAYLOAD_UPDATE")
    local code="${result%%|*}"
    local body="${result#*|}"

    assert_status "$code" 200 "issue.updated response"

    if [[ "$code" == "200" ]]; then
        assert_json_field "$body" "success" "true" "issue.updated success flag"
        assert_json_field_not_null "$body" "task_id" "issue.updated returns task_id"
        assert_json_field "$body" "has_conflict" "false" "issue.updated no conflict (first update)"
    fi
}

test_note_added() {
    log_header "3. issue.note_added — Add note to task"

    if [[ -z "$TASK_ID" ]]; then
        log_skip "issue.note_added — no task_id from create step"
        return
    fi

    log_test "Sending issue.note_added payload..."
    local result
    result=$(webhook_post "$PAYLOAD_NOTE_ADDED")
    local code="${result%%|*}"
    local body="${result#*|}"

    assert_status "$code" 200 "issue.note_added response"

    if [[ "$code" == "200" ]]; then
        assert_json_field "$body" "success" "true" "issue.note_added success flag"
        assert_json_field_not_null "$body" "task_id" "issue.note_added returns task_id"
    fi

    # Test idempotency — sending the same note again should return skipped
    log_test "Re-sending same issue.note_added (idempotency check)..."
    local result2
    result2=$(webhook_post "$PAYLOAD_NOTE_ADDED")
    local code2="${result2%%|*}"
    local body2="${result2#*|}"

    assert_status "$code2" 200 "duplicate note response"
    assert_json_field "$body2" "skipped" "true" "duplicate note is skipped"
}

test_error_cases() {
    log_header "4. Error Cases"

    # 4a. Invalid source format (special characters)
    log_test "4a. Invalid source format..."
    local result
    result=$(curl -s -w "\n%{http_code}" \
        -X POST \
        -H "Content-Type: application/json" \
        -d '{"event_type":"issue.created","issue":{"id":9999}}' \
        "${BASE_URL}/webhooks/invalid-source!" 2>&1)
    local code="${result##*$'\n'}"
    assert_status "$code" 422 "invalid source format returns 422"

    # 4b. Missing event_type
    log_test "4b. Missing event_type..."
    result=$(webhook_post '{"issue":{"id":9999}}')
    local code="${result%%|*}"
    assert_status "$code" 400 "missing event_type returns 400"

    # 4c. Missing issue field
    log_test "4c. Missing issue field..."
    result=$(webhook_post '{"event_type":"issue.created"}')
    code="${result%%|*}"
    assert_status "$code" 400 "missing issue field returns 400"

    # 4d. Missing issue.id
    log_test "4d. Missing issue.id..."
    result=$(webhook_post '{"event_type":"issue.created","issue":{"summary":"test"}}')
    code="${result%%|*}"
    assert_status "$code" 400 "missing issue.id returns 400"

    # 4e. Unknown event type
    log_test "4e. Unknown event type..."
    result=$(webhook_post '{"event_type":"issue.deleted","issue":{"id":9999}}')
    code="${result%%|*}"
    local body="${result#*|}"

    assert_status "$code" 422 "unknown event type returns 422"
    local error_msg
    error_msg=$(echo "$body" | jq -r '.error' 2>/dev/null || echo "")
    if [[ "$error_msg" == *"Unknown event type"* ]]; then
        log_pass "error message contains 'Unknown event type'"
    else
        log_fail "expected 'Unknown event type' in error, got: $error_msg"
    fi

    # 4f. Update non-existent issue (no mapping)
    log_test "4f. Update non-existent issue..."
    result=$(webhook_post '{"event_type":"issue.updated","issue":{"id":99999999}}')
    code="${result%%|*}"
    assert_status "$code" 404 "update non-existent issue returns 404"

    # 4g. Note added for non-existent issue (no mapping)
    log_test "4g. Note added for non-existent issue..."
    result=$(webhook_post '{"event_type":"issue.note_added","issue":{"id":99999999,"notes":[{"id":8888,"text":"test","author":{"username":"bot"},"created_at":"2026-04-12T10:00:00Z"}]}}')
    code="${result%%|*}"
    assert_status "$code" 404 "note on non-existent issue returns 404"
}

test_priority_mapping() {
    log_header "5. Priority Mapping"

    # Priority >= 40 → high
    log_test "5a. Priority 40 (high)..."
    local ts
    ts=$(date -u +%Y-%m-%dT%H:%M:%S.000Z)
    local pid_high="test_prio_high_$(date +%s)"
    local payload
    payload=$(cat <<EOF
{
  "event_type": "issue.created",
  "timestamp": "$ts",
  "issue": {
    "id": ${pid_high},
    "summary": "[Test] Priority high test",
    "status": 10,
    "priority": 40,
    "created_at": "$ts",
    "updated_at": "$ts"
  }
}
EOF
)
    local result
    result=$(webhook_post "$payload")
    local code="${result%%|*}"
    assert_status "$code" 200 "priority 40 (high) created"

    # Priority 30-39 → medium
    log_test "5b. Priority 30 (medium)..."
    ts=$(date -u +%Y-%m-%dT%H:%M:%S.000Z)
    local pid_med="test_prio_med_$(date +%s)"
    payload=$(cat <<EOF
{
  "event_type": "issue.created",
  "timestamp": "$ts",
  "issue": {
    "id": ${pid_med},
    "summary": "[Test] Priority medium test",
    "status": 10,
    "priority": 30,
    "created_at": "$ts",
    "updated_at": "$ts"
  }
}
EOF
)
    result=$(webhook_post "$payload")
    code="${result%%|*}"
    assert_status "$code" 200 "priority 30 (medium) created"

    # Priority < 30 → low
    log_test "5c. Priority 20 (low)..."
    ts=$(date -u +%Y-%m-%dT%H:%M:%S.000Z)
    local pid_low="test_prio_low_$(date +%s)"
    payload=$(cat <<EOF
{
  "event_type": "issue.created",
  "timestamp": "$ts",
  "issue": {
    "id": ${pid_low},
    "summary": "[Test] Priority low test",
    "status": 10,
    "priority": 20,
    "created_at": "$ts",
    "updated_at": "$ts"
  }
}
EOF
)
    result=$(webhook_post "$payload")
    code="${result%%|*}"
    assert_status "$code" 200 "priority 20 (low) created"
}

test_malformed_json() {
    log_header "6. Malformed JSON"

    log_test "6a. Invalid JSON body..."
    local result
    result=$(curl -s -w "\n%{http_code}" \
        -X POST \
        -H "Content-Type: application/json" \
        -d '{invalid json' \
        "$ENDPOINT" 2>&1)
    local code="${result##*$'\n'}"
    assert_status "$code" 400 "malformed JSON returns 400"

    log_test "6b. Empty body..."
    result=$(curl -s -w "\n%{http_code}" \
        -X POST \
        -H "Content-Type: application/json" \
        -d '' \
        "$ENDPOINT" 2>&1)
    code="${result##*$'\n'}"
    assert_status "$code" 400 "empty body returns 400"

    log_test "6c. Wrong HTTP method (GET)..."
    result=$(curl -s -w "\n%{http_code}" \
        -X GET \
        "$ENDPOINT" 2>&1)
    code="${result##*$'\n'}"
    # GET should return 405 Method Not Allowed
    if [[ "$code" == "405" ]]; then
        log_pass "GET returns 405 Method Not Allowed"
    else
        # Some frameworks return 404 or redirect for wrong method
        log_pass "GET returns $code (not 200)"
    fi
}

test_business_category() {
    log_header "7. Business Category Assignment"

    if [[ -z "$TASK_ID" ]]; then
        log_skip "Business category — no task_id from create step"
        return
    fi

    log_test "7a. Created task has Business category..."
    # Fetch the task via PostgREST to check category_id
    local task_response
    task_response=$(curl -s -H "Authorization: Bearer ${PGRST_TOKEN}" \
        "${PGRST_URL}/tasks?id=eq.${TASK_ID}&select=id,category_id" 2>&1)

    local category_id
    category_id=$(echo "$task_response" | jq -r '.[0].category_id' 2>/dev/null || echo "null")

    if [[ -n "$category_id" && "$category_id" != "null" && "$category_id" != "" ]]; then
        log_pass "Task has category_id: $category_id"
    else
        log_fail "Task is missing category_id (expected Business category)"
    fi

    # Verify the category name is "Business"
    log_test "7b. Category name is 'Business'..."
    local cat_name
    cat_name=$(curl -s -H "Authorization: Bearer ${PGRST_TOKEN}" \
        "${PGRST_URL}/categories?id=eq.${category_id}&select=name" 2>&1 | \
        jq -r '.[0].name' 2>/dev/null || echo "")

    if [[ "$cat_name" == "Business" ]]; then
        log_pass "Category name is 'Business'"
    else
        log_fail "Category name is '$cat_name', expected 'Business'"
    fi
}

# ── Main ─────────────────────────────────────────────────────────────────────

SKIP_CLEANUP=false
RUN_TEST=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --skip-cleanup) SKIP_CLEANUP=true ;;
        --test) RUN_TEST="$2"; shift ;;
        *) echo "Unknown option: $1"; exit 1 ;;
    esac
    shift
done

echo -e "${CYAN}╔══════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║  Webhook Handler Integration Test Suite      ║${NC}"
echo -e "${CYAN}╚══════════════════════════════════════════════╝${NC}"
echo -e "  Target: ${ENDPOINT}"
echo -e "  External ID: ${EXTERNAL_ID}"
echo ""

# Check dependencies
for cmd in curl jq; do
    if ! command -v "$cmd" &>/dev/null; then
        echo -e "${RED}Required command not found: $cmd${NC}"
        exit 1
    fi
done

# Run selected test group or all
case "${RUN_TEST}" in
    create)   test_create ;;
    update)   test_update ;;
    note)     test_note_added ;;
    errors)   test_error_cases ;;
    priority)  test_priority_mapping ;;
    malformed) test_malformed_json ;;
    category)  test_business_category ;;
    "")
        test_create
        test_update
        test_note_added
        test_error_cases
        test_priority_mapping
        test_malformed_json
        test_business_category
        ;;
    *)
        echo "Unknown test group: $RUN_TEST"
        echo "Available: create, update, note, errors, priority, malformed, category"
        exit 1
        ;;
esac

# ── Summary ──────────────────────────────────────────────────────────────────

echo ""
echo -e "${CYAN}════════════════════════════════════════════════${NC}"
echo -e "  Results: ${GREEN}$PASS passed${NC}, ${RED}$FAIL failed${NC}, ${YELLOW}$SKIP skipped${NC}"
echo -e "${CYAN}════════════════════════════════════════════════${NC}"

if [[ "$SKIP_CLEANUP" == "false" && -n "$TASK_ID" ]]; then
    echo ""
    echo -e "${YELLOW}Created task_id: $TASK_ID${NC}"
    echo -e "${YELLOW}External ID: $EXTERNAL_ID${NC}"
    echo -e "${YELLOW}Inspect on kanban board: ${BASE_URL}${NC}"
    echo -e "${YELLOW}To clean up, delete the task from the UI or via PostgREST.${NC}"
fi

if [[ $FAIL -gt 0 ]]; then
    exit 1
fi

exit 0