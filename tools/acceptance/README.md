# Acceptance Suite Runner

Phase acceptance scripts are organized for repeatable execution.

## Structure

- `suites/minimal/`: lightweight regression set
- `suites/full/`: heavier integrated acceptance set
- `acceptance-suites.json`: suite definitions
- `run-suite.php`: suite runner

## Usage

Run minimal regression set:

```bash
php tools/acceptance/run-suite.php --suite=minimal
```

Run full integrated set:

```bash
php tools/acceptance/run-suite.php --suite=full
```

## Configuration

Each acceptance script uses `.env` and existing DB/session prerequisites.

Tenant-dependent values and URLs are not hardcoded in this runner; scripts resolve these from `.env` and database state.

## Exit codes

- `0`: all scripts in suite passed
- `1`: one or more scripts failed
- `2`: invalid suite/config issue
