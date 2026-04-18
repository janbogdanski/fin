import { Controller } from "@hotwired/stimulus"

/**
 * Import Wizard Controller
 *
 * Manages a single-broker, multi-file upload form:
 * - User picks broker, selects/drops one or more files
 * - File list is displayed below the dropzone
 * - Submit button is enabled once at least one file is selected
 * - Running total bar shows accumulated import stats (populated from server after import)
 *
 * Targets:
 *   fileList    - <ul> element where selected filenames are rendered
 *   submitBtn   - submit button (disabled until files chosen)
 *   runningTotal  - element displaying the running total bar
 *   totalCount    - text element for total transaction count
 *   totalBrokers  - text element for total broker count
 *   finishButton  - "Zakoncz i oblicz podatek" button
 *
 * Values:
 *   importedFiles     - number of successfully imported files (from server)
 *   totalTransactions - running total of transactions (from server)
 *   totalBrokers      - number of distinct brokers imported (from server)
 */
export default class extends Controller {
    static targets = [
        "fileList",
        "submitBtn",
        "runningTotal",
        "totalCount",
        "totalBrokers",
        "finishButton",
    ]

    static values = {
        importedFiles:     { type: Number, default: 0 },
        totalTransactions: { type: Number, default: 0 },
        totalBrokers:      { type: Number, default: 0 },
    }

    connect() {
        this.updateRunningTotal()
    }

    // ── File selection via <input type="file"> ──────────────────────────────

    fileSelected(event) {
        const files = Array.from(event.target.files)
        this._showFiles(files)
    }

    // ── Drag-and-drop ───────────────────────────────────────────────────────

    dragover(event) {
        event.preventDefault()
        event.currentTarget.classList.add("border-blue-500", "bg-blue-50")
    }

    dragleave(event) {
        event.preventDefault()
        event.currentTarget.classList.remove("border-blue-500", "bg-blue-50")
    }

    drop(event) {
        event.preventDefault()
        event.currentTarget.classList.remove("border-blue-500", "bg-blue-50")

        const files = Array.from(event.dataTransfer.files)
        if (!files.length) return

        // Assign dropped files to the hidden file input so they are included in form submit
        const input = this.element.querySelector("input[type='file']")
        if (input) {
            const dt = new DataTransfer()
            files.forEach(f => dt.items.add(f))
            input.files = dt.files
        }

        this._showFiles(files)
    }

    // ── Running total (updated by server via data-values after import) ──────

    updateRunningTotal() {
        if (!this.hasRunningTotalTarget) return

        const hasImports = this.importedFilesValue > 0
        this.runningTotalTarget.classList.toggle("hidden", !hasImports)

        if (this.hasTotalCountTarget) {
            this.totalCountTarget.textContent = this.totalTransactionsValue
        }
        if (this.hasTotalBrokersTarget) {
            this.totalBrokersTarget.textContent = this.totalBrokersValue
        }
        if (this.hasFinishButtonTarget) {
            this.finishButtonTarget.classList.toggle("hidden", !hasImports)
        }
    }

    importedFilesValueChanged()     { this.updateRunningTotal() }
    totalTransactionsValueChanged() { this.updateRunningTotal() }
    totalBrokersValueChanged()      { this.updateRunningTotal() }

    // ── Private ─────────────────────────────────────────────────────────────

    _showFiles(files) {
        if (!files.length) return

        if (this.hasFileListTarget) {
            this.fileListTarget.innerHTML = files
                .map(f => `<li class="flex items-center gap-2 text-sm text-gray-700">
                    <svg class="h-4 w-4 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="truncate">${this._escapeHtml(f.name)}</span>
                </li>`)
                .join("")
            this.fileListTarget.classList.remove("hidden")
        }

        if (this.hasSubmitBtnTarget) {
            this.submitBtnTarget.disabled = false
        }
    }

    _escapeHtml(str) {
        return str
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
    }
}
