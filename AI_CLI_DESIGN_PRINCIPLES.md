# CLI Design Principles for AI Systems

This document outlines the characteristics that make CLIs easier and more reliable for AI systems to use. These principles apply to any CLI, not just this one.

## 1. Structured Output Formats

### ✅ JSON/Structured Output
**Why it matters**: AI systems need to parse output reliably. Free-form text is ambiguous.

```bash
# ❌ Hard for AI to parse
$ git status
On branch main
Changes not staged for commit:
  modified:   file.txt

# ✅ Easy for AI to parse
$ git status --porcelain
M  file.txt

# ✅ Even better
$ kubectl get pods -o json
{
  "items": [
    {"name": "pod-1", "status": "Running"}
  ]
}
```

**Best practices**:
- Provide `--json`, `--yaml`, or `--format=json` flags
- Use consistent structure across all commands
- Include metadata (timestamps, versions, etc.)

## 2. Consistent Command Patterns

### ✅ Predictable Naming
**Why it matters**: AI can infer command structure and reduce errors.

```bash
# ✅ Consistent pattern
$ app create <resource> <name>
$ app get <resource> <id>
$ app list <resource>
$ app update <resource> <id> <data>
$ app delete <resource> <id>

# ❌ Inconsistent
$ app new-user <name>      # Why "new-user" not "create user"?
$ app show-user <id>       # Why "show" not "get"?
$ app rm-user <id>         # Why "rm" not "delete"?
```

**Best practices**:
- Use consistent verbs: `create`, `get`, `list`, `update`, `delete`
- Use consistent resource naming
- Follow REST-like patterns when possible

## 3. Clear, Structured Error Messages

### ✅ Machine-Readable Errors
**Why it matters**: AI needs to understand failures programmatically.

```bash
# ❌ Vague error
$ app deploy
Error: Something went wrong

# ✅ Structured error
$ app deploy
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Invalid configuration",
    "fields": {
      "region": ["Region is required"],
      "instance_type": ["Invalid instance type"]
    }
  }
}
```

**Best practices**:
- Use error codes (not just messages)
- Include field-level validation errors
- Provide actionable error messages
- Use consistent error structure

## 4. Non-Interactive Modes

### ✅ Flags Over Prompts
**Why it matters**: AI can't interact with prompts. Everything must be command-line flags.

```bash
# ❌ Interactive (hard for AI)
$ app create
Enter name: [prompt]
Enter region: [prompt]
Confirm? [y/n]

# ✅ Non-interactive (easy for AI)
$ app create --name=myapp --region=us-east-1 --yes
```

**Best practices**:
- Provide flags for all inputs
- Use `--yes` or `--force` to skip confirmations
- Support environment variables
- Support config files for complex inputs

## 5. Idempotent Operations

### ✅ Safe to Retry
**Why it matters**: AI may retry operations. Commands should be safe to run multiple times.

```bash
# ✅ Idempotent
$ app set-config --key=value
# Running multiple times has same effect

# ❌ Not idempotent
$ app increment-counter
# Each run changes state differently
```

**Best practices**:
- Make operations idempotent when possible
- Use "upsert" semantics (create or update)
- Document idempotency in help text

## 6. Dry-Run / Preview Modes

### ✅ Test Before Execute
**Why it matters**: AI can validate operations before executing them.

```bash
# ✅ Dry-run mode
$ app deploy --dry-run
Would deploy:
  - Application: myapp
  - Region: us-east-1
  - Instances: 2

# ✅ Preview changes
$ app update-config --preview
Changes:
  - region: us-east-1 → us-east-2
  - instances: 1 → 2
```

**Best practices**:
- Provide `--dry-run` or `--preview` flags
- Show what would happen without executing
- Make output format identical to real execution

## 7. Comprehensive Help System

### ✅ Machine-Readable Help
**Why it matters**: AI needs to discover available commands and options.

```bash
# ✅ Structured help
$ app --help-json
{
  "commands": [
    {
      "name": "deploy",
      "description": "Deploy an application",
      "options": [
        {
          "name": "--region",
          "type": "string",
          "required": true,
          "description": "AWS region"
        }
      ]
    }
  ]
}

# ✅ At minimum, consistent help format
$ app deploy --help
Usage: app deploy [OPTIONS]

Options:
  --region TEXT    AWS region (required)
  --instances INT  Number of instances [default: 1]
```

**Best practices**:
- Provide `--help-json` or structured help
- Include type information for options
- Mark required vs optional clearly
- Include examples in help

## 8. Clear Exit Codes

### ✅ Standard Exit Codes
**Why it matters**: AI needs to know if a command succeeded or failed.

```bash
# ✅ Standard exit codes
0  - Success
1  - General error
2  - Misuse of shell command
64 - User input error
65 - Data format error
66 - Cannot open input
67 - Addressee unknown
68 - Host name unknown
69 - Service unavailable
70 - Internal software error
71 - System error
72 - Critical OS file missing
73 - Can't create output file
74 - I/O error
75 - Temp failure
76 - Remote error in protocol
77 - Permission denied
78 - Configuration error
```

**Best practices**:
- Use standard exit codes
- Document exit codes in help
- Be consistent across commands
- Use non-zero for any failure

## 9. State Management

### ✅ Query Current State
**Why it matters**: AI needs to check current state before making changes.

```bash
# ✅ Check state
$ app status
{
  "status": "running",
  "version": "1.2.3",
  "uptime": "2h 15m"
}

# ✅ List resources
$ app list
[
  {"id": "1", "name": "app1", "status": "running"},
  {"id": "2", "name": "app2", "status": "stopped"}
]
```

**Best practices**:
- Provide status/state commands
- Make state queryable (not just visible in UI)
- Include timestamps in state
- Show relationships between resources

## 10. Type Safety & Validation

### ✅ Clear Input Types
**Why it matters**: AI needs to know what format inputs should be.

```bash
# ✅ Type information
$ app create --name=STRING --count=INT --enabled=BOOL --tags=ARRAY

# ✅ Validation errors
$ app create --count=abc
Error: Invalid value for --count: 'abc' is not an integer
```

**Best practices**:
- Validate inputs early
- Provide clear type information
- Show validation errors before execution
- Support type coercion where safe

## 11. Atomic Operations

### ✅ All-or-Nothing
**Why it matters**: AI needs predictable outcomes. Partial failures are confusing.

```bash
# ✅ Atomic
$ app deploy
# Either fully succeeds or fully fails (rolls back)

# ❌ Non-atomic
$ app deploy
# Partially succeeds, leaves system in inconsistent state
```

**Best practices**:
- Make operations atomic when possible
- Use transactions for multi-step operations
- Provide rollback mechanisms
- Document partial failure scenarios

## 12. Progress Indicators

### ✅ Structured Progress
**Why it matters**: AI needs to know if operations are still running.

```bash
# ✅ Structured progress
$ app deploy --json
{"status": "building", "progress": 45, "stage": "compile"}
{"status": "deploying", "progress": 80, "stage": "deploy"}
{"status": "complete", "progress": 100}

# ✅ At minimum, non-blocking
$ app deploy --background
Job ID: 12345
$ app status --job=12345
```

**Best practices**:
- Provide structured progress output
- Support background/async operations
- Allow querying job status
- Include time estimates when possible

## 13. Logging & Observability

### ✅ Structured Logs
**Why it matters**: AI needs to understand what happened.

```bash
# ✅ Structured logs
$ app deploy --log-format=json
{"timestamp": "2024-01-01T12:00:00Z", "level": "info", "message": "Starting deployment"}
{"timestamp": "2024-01-01T12:00:05Z", "level": "info", "message": "Build complete"}

# ✅ Log levels
$ app deploy --log-level=debug
```

**Best practices**:
- Support structured logging (JSON)
- Provide log levels
- Include timestamps
- Make logs queryable

## 14. Configuration Management

### ✅ Multiple Config Sources
**Why it matters**: AI needs flexible ways to provide configuration.

```bash
# ✅ Support multiple sources (in priority order)
1. Command-line flags
2. Environment variables
3. Config files
4. Defaults

$ APP_REGION=us-east-1 app deploy --instances=2
# Uses env var for region, flag for instances
```

**Best practices**:
- Support config files
- Support environment variables
- Document precedence
- Provide config validation

## 15. Versioning & Compatibility

### ✅ Version Information
**Why it matters**: AI needs to know what features are available.

```bash
# ✅ Version info
$ app --version
app version 1.2.3
API version: v2

# ✅ Feature flags
$ app --features
{
  "features": {
    "deploy": true,
    "rollback": true,
    "scale": false  # Not available in this version
  }
}
```

**Best practices**:
- Provide version information
- Support feature detection
- Document breaking changes
- Provide migration paths

## 16. Batch Operations

### ✅ Process Multiple Items
**Why it matters**: AI often needs to process multiple items efficiently.

```bash
# ✅ Batch operations
$ app deploy --apps=app1,app2,app3
# Or
$ app deploy --apps-file=apps.txt

# ✅ Parallel execution
$ app deploy --parallel --max-concurrent=5
```

**Best practices**:
- Support batch operations
- Allow parallel execution with limits
- Provide progress for batches
- Handle partial batch failures gracefully

## 17. Query & Filter Capabilities

### ✅ Flexible Querying
**Why it matters**: AI needs to find specific resources efficiently.

```bash
# ✅ Filtering
$ app list --filter="status=running&region=us-east-1"

# ✅ Querying
$ app list --query=".items[?status=='running']"

# ✅ Sorting
$ app list --sort="name" --order="desc"
```

**Best practices**:
- Support filtering
- Support sorting
- Support pagination
- Use standard query syntax when possible

## 18. Testing & Validation

### ✅ Validation Before Execution
**Why it matters**: AI should validate before executing expensive operations.

```bash
# ✅ Validate config
$ app deploy --validate-only
✓ Configuration valid
✓ Dependencies available
✓ Permissions sufficient

# ✅ Check prerequisites
$ app deploy --check
Checking prerequisites...
✓ API key configured
✓ Network connectivity
✗ Insufficient quota (need 2, have 1)
```

**Best practices**:
- Provide validation commands
- Check prerequisites
- Validate early
- Provide actionable feedback

## Summary: The AI-Friendly CLI Checklist

A CLI is AI-friendly when it has:

- [ ] **Structured output** (JSON/YAML flags)
- [ ] **Consistent command patterns** (predictable naming)
- [ ] **Structured errors** (codes, field-level errors)
- [ ] **Non-interactive mode** (all inputs via flags)
- [ ] **Idempotent operations** (safe to retry)
- [ ] **Dry-run mode** (preview before execute)
- [ ] **Machine-readable help** (JSON help format)
- [ ] **Standard exit codes** (0 = success, non-zero = failure)
- [ ] **State queries** (check current state)
- [ ] **Type validation** (clear input types)
- [ ] **Atomic operations** (all-or-nothing)
- [ ] **Structured progress** (JSON progress updates)
- [ ] **Structured logging** (JSON logs)
- [ ] **Config flexibility** (files, env vars, flags)
- [ ] **Version info** (feature detection)
- [ ] **Batch operations** (process multiple items)
- [ ] **Query/filter** (find specific resources)
- [ ] **Validation commands** (check before execute)

## Real-World Examples

### Excellent AI-Friendly CLIs

**kubectl**:
- `-o json` for structured output
- Consistent resource/verb pattern
- Dry-run with `--dry-run=client`
- Clear exit codes

**terraform**:
- JSON output mode
- Plan before apply (dry-run)
- State management
- Structured errors

**aws-cli**:
- `--output json`
- Consistent service/operation pattern
- Comprehensive help
- Structured errors

### Less AI-Friendly CLIs

**git** (without flags):
- Free-form text output
- Inconsistent command patterns
- Interactive prompts
- Hard to parse status

**docker** (basic usage):
- Text-based output
- Inconsistent formatting
- Limited structured output

## Key Takeaway

**The most important principle**: **Provide machine-readable alternatives for everything**. If a human can read it, an AI should be able to parse it programmatically. This means:

1. JSON/YAML output flags
2. Structured errors with codes
3. Non-interactive modes
4. Consistent patterns
5. Comprehensive validation

When in doubt, ask: "Can an AI system reliably parse and act on this output without ambiguity?"
