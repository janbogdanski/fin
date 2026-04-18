import { Controller } from "@hotwired/stimulus"

/**
 * Import Wizard Controller
 *
 * Manages the multi-broker import wizard flow:
 * - Step 1: User selects broker + uploads CSV -> form submits to server
 * - After successful import, server renders results
 * - "Dodaj kolejny broker" adds a new upload row dynamically
 * - Running total shows accumulated import stats across rows
 * - Multiple files can be dropped or selected at once — one row per file
 *
 * Targets:
 *   rowTemplate   - <template> element containing the upload row markup
 *   rowContainer  - container where upload rows are appended
 *   runningTotal  - element displaying the running total bar
 *   totalCount    - text element for total transaction count
 *   totalBrokers  - text element for total broker count
 *   finishButton  - "Zakoncz i oblicz podatek" button
 *   addButton     - "Dodaj kolejny broker" button
 *
 * Values:
 *   importedFiles - number of successfully imported files (from server)
 *   totalTransactions - running total of transactions (from server)
 *   totalBrokers - number of distinct brokers imported (from server)
 */
export default class extends Controller {
    static targets = [
        "rowTemplate",
        "rowContainer",
        "runningTotal",
        "totalCount",
        "totalBrokers",
        "finishButton",
        "addButton",
    ]

    static values = {
        importedFiles: { type: Number, default: 0 },
        totalTransactions: { type: Number, default: 0 },
        totalBrokers: { type: Number, default: 0 },
    }

    connect() {
        this.rowIndex = 0
        this.updateRunningTotal()
    }

    /**
     * Adds a new broker upload row from the template.
     * Returns the newly created row element.
     */
    addRow(event) {
        if (event) event.preventDefault()

        this.rowIndex++

        const template = this.rowTemplateTarget
        const clone = template.content.cloneNode(true)

        const row = clone.querySelector("[data-import-wizard-row]")
        if (row) {
            row.dataset.rowIndex = this.rowIndex
        }

        const fileInput = clone.querySelector("input[type='file']")
        if (fileInput) {
            fileInput.value = ""
        }

        const submitBtn = clone.querySelector("[data-role='row-submit']")
        if (submitBtn) {
            submitBtn.disabled = true
        }

        const filenameDisplay = clone.querySelector("[data-role='filename-display']")
        if (filenameDisplay) {
            filenameDisplay.textContent = ""
        }

        this.rowContainerTarget.appendChild(clone)

        const newRow = this.rowContainerTarget.lastElementChild
        const brokerSelect = newRow.querySelector("select")
        if (brokerSelect) {
            brokerSelect.focus()
        }

        return newRow
    }

    /**
     * Assigns a File object to a row's file input and activates its submit button.
     */
    _assignFileToRow(row, file) {
        const fileInput = row.querySelector("input[type='file']")
        if (fileInput) {
            const dt = new DataTransfer()
            dt.items.add(file)
            fileInput.files = dt.files
        }

        const filenameDisplay = row.querySelector("[data-role='filename-display']")
        if (filenameDisplay) {
            filenameDisplay.textContent = file.name
        }

        const submitBtn = row.querySelector("[data-role='row-submit']")
        if (submitBtn) {
            submitBtn.disabled = false
        }
    }

    /**
     * Handles file selection in a row (via input change).
     * When multiple files are selected, creates additional rows for the extras.
     */
    fileSelected(event) {
        const row = event.target.closest("[data-import-wizard-row]")
        if (!row) return

        const files = Array.from(event.target.files)
        if (!files.length) return

        // Assign first file to this row
        const firstFile = files[0]

        const filenameDisplay = row.querySelector("[data-role='filename-display']")
        if (filenameDisplay) {
            filenameDisplay.textContent = firstFile.name
        }

        const submitBtn = row.querySelector("[data-role='row-submit']")
        if (submitBtn) {
            submitBtn.disabled = false
        }

        // Create additional rows for remaining files
        for (let i = 1; i < files.length; i++) {
            const newRow = this.addRow(null)
            this._assignFileToRow(newRow, files[i])
        }
    }

    /**
     * Handles dragover on a row's dropzone.
     */
    rowDragover(event) {
        event.preventDefault()
        const dropzone = event.currentTarget
        dropzone.classList.add("border-blue-500", "bg-blue-50")
    }

    /**
     * Handles dragleave on a row's dropzone.
     */
    rowDragleave(event) {
        event.preventDefault()
        const dropzone = event.currentTarget
        dropzone.classList.remove("border-blue-500", "bg-blue-50")
    }

    /**
     * Handles drop on a row's dropzone.
     * When multiple files are dropped, creates additional rows for the extras.
     */
    rowDrop(event) {
        event.preventDefault()
        const dropzone = event.currentTarget
        dropzone.classList.remove("border-blue-500", "bg-blue-50")

        const files = Array.from(event.dataTransfer.files)
        if (!files.length) return

        const row = dropzone.closest("[data-import-wizard-row]")
        if (!row) return

        // Assign first file to the target row
        this._assignFileToRow(row, files[0])

        // Create additional rows for remaining files
        for (let i = 1; i < files.length; i++) {
            const newRow = this.addRow(null)
            this._assignFileToRow(newRow, files[i])
        }
    }

    /**
     * Removes a broker upload row.
     */
    removeRow(event) {
        event.preventDefault()

        const row = event.target.closest("[data-import-wizard-row]")
        if (!row) return

        const rows = this.rowContainerTarget.querySelectorAll("[data-import-wizard-row]")
        if (rows.length <= 1) return

        row.remove()
    }

    /**
     * Updates the running total display based on current values.
     */
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

    importedFilesValueChanged() {
        this.updateRunningTotal()
    }

    totalTransactionsValueChanged() {
        this.updateRunningTotal()
    }

    totalBrokersValueChanged() {
        this.updateRunningTotal()
    }
}
