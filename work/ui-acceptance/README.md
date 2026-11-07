# UI acceptance artifacts

This directory stores disposable run metadata and browser evidence. Business operations are executed through Playwright in `tests/Browser`. Database checks are read-only and must use the dedicated MySQL/MariaDB QA schema.

Credentials are supplied through `UI_AUTH_<ROLE>_PASSWORD` environment variables and are never committed.
