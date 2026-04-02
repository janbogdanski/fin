import { Controller } from "@hotwired/stimulus"

/**
 * Client-side table filtering.
 * Filters rows based on data-* attributes matching filter inputs.
 */
export default class extends Controller {
    static targets = ["broker", "instrument", "type", "table"]

    apply() {
        const brokerFilter = this.hasBrokerTarget ? this.brokerTarget.value : ""
        const instrumentFilter = this.hasInstrumentTarget ? this.instrumentTarget.value.toUpperCase().trim() : ""
        const typeFilter = this.hasTypeTarget ? this.typeTarget.value : ""

        const table = this.hasTableTarget ? this.tableTarget : this.element.querySelector("table")
        if (!table) {
            return
        }

        const tbody = table.querySelector("tbody")
        if (!tbody) {
            return
        }

        const rows = tbody.querySelectorAll("tr")

        rows.forEach(row => {
            let visible = true

            if (brokerFilter && row.dataset.broker !== brokerFilter) {
                visible = false
            }

            if (instrumentFilter && !row.dataset.instrument?.toUpperCase().includes(instrumentFilter)) {
                visible = false
            }

            if (typeFilter && row.dataset.type !== typeFilter) {
                visible = false
            }

            row.style.display = visible ? "" : "none"
        })
    }
}
