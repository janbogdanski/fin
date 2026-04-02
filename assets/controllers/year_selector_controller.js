import { Controller } from "@hotwired/stimulus"

/**
 * Year selector — navigates to the dashboard for the selected tax year.
 * Uses Turbo.visit for SPA-like navigation without full page reload.
 */
export default class extends Controller {
    static targets = ["select"]

    change() {
        const year = this.selectTarget.value
        if (!year) {
            return
        }

        // Navigate to dashboard with new year context
        // When persistence is ready, this will load data for the selected year
        window.Turbo.visit(`/dashboard/calculation/${year}`, { action: "replace" })
    }
}
