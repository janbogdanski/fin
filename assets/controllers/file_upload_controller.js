import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["input", "dropzone", "filename", "submit"]

    connect() {
        this.submitTarget.disabled = true
    }

    dragover(event) {
        event.preventDefault()
        this.dropzoneTarget.classList.add("border-blue-500", "bg-blue-50")
    }

    dragleave(event) {
        event.preventDefault()
        this.dropzoneTarget.classList.remove("border-blue-500", "bg-blue-50")
    }

    drop(event) {
        event.preventDefault()
        this.dropzoneTarget.classList.remove("border-blue-500", "bg-blue-50")

        const file = event.dataTransfer.files[0]
        if (!file) {
            return
        }

        this.inputTarget.files = event.dataTransfer.files
        this.updateFilename(file.name)
    }

    select() {
        const file = this.inputTarget.files[0]
        if (!file) {
            return
        }

        this.updateFilename(file.name)
    }

    updateFilename(name) {
        this.filenameTarget.textContent = name
        this.submitTarget.disabled = false
    }
}
