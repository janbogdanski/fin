import { Controller } from "@hotwired/stimulus"

/**
 * Client-side table sorting.
 * Reads data-* attributes from <tr> elements to determine sort values.
 * Click column header to toggle ascending/descending.
 */
export default class extends Controller {
    static targets = ["body"]

    connect() {
        this.currentKey = null
        this.ascending = true
    }

    sort(event) {
        const key = event.params.key
        if (!key) {
            return
        }

        if (this.currentKey === key) {
            this.ascending = !this.ascending
        } else {
            this.currentKey = key
            this.ascending = true
        }

        const tbody = this.bodyTarget
        const rows = Array.from(tbody.querySelectorAll("tr"))

        rows.sort((a, b) => {
            const aVal = a.dataset[key] || ""
            const bVal = b.dataset[key] || ""

            const aNum = parseFloat(aVal)
            const bNum = parseFloat(bVal)

            let result
            if (!isNaN(aNum) && !isNaN(bNum)) {
                result = aNum - bNum
            } else {
                result = aVal.localeCompare(bVal, "pl")
            }

            return this.ascending ? result : -result
        })

        rows.forEach(row => tbody.appendChild(row))
    }
}
