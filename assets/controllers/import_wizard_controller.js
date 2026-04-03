import { Controller } from "@hotwired/stimulus"

/**
 * Import Wizard Controller
 *
 * Manages the multi-broker import wizard flow:
 * - Step 1: User selects broker + uploads CSV -> form submits to server
 * - After successful import, server renders results
 * - "Dodaj kolejny broker" adds a new upload row dynamically
 * - Running total shows accumulated import stats across rows
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
     */
    addRow(event) {
        event.preventDefault()

        this.rowIndex++

        const template = this.rowTemplateTarget
        const clone = template.content.cloneNode(true)

        // Update IDs/names to be unique per row
        const row = clone.querySelector("[data-import-wizard-row]")
        if (row) {
            row.dataset.rowIndex = this.rowIndex
        }

        // Reset file input and submit button state
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

        // Focus the broker select in the new row for keyboard accessibility
        const newRow = this.rowContainerTarget.lastElementChild
        const brokerSelect = newRow.querySelector("select")
        if (brokerSelect) {
            brokerSelect.focus()
        }
    }

    /**
     * Handles file selection in a row (via input change or drop).
     * Enables the row's submit button and shows filename.
     */
    fileSelected(event) {
        const row = event.target.closest("[data-import-wizard-row]")
        if (!row) return

        const file = event.target.files[0]
        if (!file) return

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
     */
    rowDrop(event) {
        event.preventDefault()
        const dropzone = event.currentTarget
        dropzone.classList.remove("border-blue-500", "bg-blue-50")

        const file = event.dataTransfer.files[0]
        if (!file) return

        const row = dropzone.closest("[data-import-wizard-row]")
        if (!row) return

        const fileInput = row.querySelector("input[type='file']")
        if (fileInput) {
            fileInput.files = event.dataTransfer.files
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
     * Removes a broker upload row.
     */
    removeRow(event) {
        event.preventDefault()

        const row = event.target.closest("[data-import-wizard-row]")
        if (!row) return

        // Don't remove if it's the last row
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
