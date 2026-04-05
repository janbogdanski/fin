#!/usr/bin/env bash
# bin/check-adr-drift.sh
#
# ADR vs Code Drift Detection
#
# For each ADR file in docs/adr/, finds PHP class/interface/namespace identifiers
# (patterns like App\Foo\Bar) and verifies the corresponding file exists in src/.
#
# Exits 1 if any referenced identifier has drifted (class renamed or deleted).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
ADR_DIR="${PROJECT_ROOT}/docs/adr"
SRC_DIR="${PROJECT_ROOT}/src"

if [[ ! -d "${ADR_DIR}" ]]; then
  echo "ADR directory not found: ${ADR_DIR}"
  exit 0
fi

drift_found=0

while IFS= read -r -d '' adr_file; do
  adr_name="$(basename "${adr_file}")"

  # Extract fully-qualified PHP class/interface/namespace references.
  # Matches patterns like App\Foo\Bar (at least two segments, starts with App\).
  mapfile -t identifiers < <(
    grep -oE 'App(\\[A-Za-z0-9_]+){2,}' "${adr_file}" \
      | sort -u \
      || true
  )

  for fqcn in "${identifiers[@]}"; do
    # Convert FQCN to a relative file path: App\Foo\Bar -> Foo/Bar.php
    # Strip leading "App\" then replace remaining backslashes with slashes.
    relative_path="${fqcn#App\\}"
    relative_path="${relative_path//\\/\/}.php"
    target_file="${SRC_DIR}/${relative_path}"

    if [[ ! -f "${target_file}" ]]; then
      echo "[DRIFT] ${adr_name} references '${fqcn}' but file not found: src/${relative_path}"
      drift_found=1
    fi
  done

done < <(find "${ADR_DIR}" -maxdepth 1 -name "*.md" -print0 | sort -z)

if [[ "${drift_found}" -eq 1 ]]; then
  echo ""
  echo "ADR drift detected. Referenced symbols no longer exist in src/."
  echo "Either update the ADR to reflect the current class names, or restore the missing symbols."
  exit 1
fi

echo "ADR drift check passed — all referenced symbols exist in src/."
exit 0
