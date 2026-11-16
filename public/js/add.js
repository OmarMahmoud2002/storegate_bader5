class ExcelUploader {
  constructor() {
    this.maxFileSize = 50 * 1024 * 1024
    this.allowedTypes = [
      "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      "application/vnd.ms-excel",
    ]
    this.progressInterval = null
    this.initializeElements()
    this.bindEvents()
  }

  initializeElements() {
    this.uploadZone = document.getElementById("uploadZone")
    this.fileInput = document.getElementById("excelFile")
    this.fileInfo = document.getElementById("fileInfo")
    this.fileName = document.getElementById("fileName")
    this.fileSize = document.getElementById("fileSize")
    this.removeFileBtn = document.getElementById("removeFile")
    this.uploadBtn = document.getElementById("uploadBtn")
    this.btnText = document.getElementById("btnText")
    this.uploadForm = document.getElementById("uploadForm")
    this.progressContainer = document.getElementById("progressContainer")
    this.progressTitle = document.getElementById("progressTitle")
    this.progressBar = document.getElementById("progressBar")
    this.progressPercent = document.getElementById("progressPercent")
    this.progressStatus = document.getElementById("progressStatus")
    this.confirmationPopup = document.getElementById("staleFilePopup")
    this.confirmationTitle = document.getElementById("staleFilePopupTitle")
    this.confirmationMessage = document.getElementById("staleFilePopupMessage")
    this.confirmationIcon = document.getElementById("staleFilePopupIcon")
    this.confirmStaleUpload = document.getElementById("confirmStaleUpload")
    this.cancelStaleUpload = document.getElementById("cancelStaleUpload")
    this.closeStalePopup = document.getElementById("staleFilePopupClose")
  }

  bindEvents() {
    this.uploadZone.addEventListener("click", () => this.fileInput.click())
    this.uploadZone.addEventListener("dragover", this.handleDragOver.bind(this))
    this.uploadZone.addEventListener("dragleave", this.handleDragLeave.bind(this))
    this.uploadZone.addEventListener("drop", this.handleDrop.bind(this))
    this.fileInput.addEventListener("change", this.handleFileSelect.bind(this))
    this.removeFileBtn.addEventListener("click", this.removeFile.bind(this))
    this.uploadForm.addEventListener("submit", this.handleFormSubmit.bind(this))

    this.confirmStaleUpload.addEventListener("click", () => {
      this.hideConfirmation()
      this.startUpload(true)
    })
    this.cancelStaleUpload.addEventListener("click", () => this.hideConfirmation())
    this.closeStalePopup.addEventListener("click", () => this.hideConfirmation())
    this.confirmationPopup.addEventListener("click", (event) => {
      if (event.target === this.confirmationPopup) this.hideConfirmation()
    })
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && this.confirmationPopup.classList.contains("is-open")) {
        this.hideConfirmation()
      }
    })
  }

  handleDragOver(event) {
    event.preventDefault()
    this.uploadZone.classList.add("dragover")
  }

  handleDragLeave(event) {
    event.preventDefault()
    this.uploadZone.classList.remove("dragover")
  }

  handleDrop(event) {
    event.preventDefault()
    this.uploadZone.classList.remove("dragover")

    const file = event.dataTransfer.files[0]
    if (!file) return

    const transfer = new DataTransfer()
    transfer.items.add(file)
    this.fileInput.files = transfer.files
    this.processFile(file)
  }

  handleFileSelect(event) {
    const file = event.target.files[0]
    if (file) this.processFile(file)
  }

  processFile(file) {
    const hasExcelExtension = /\.(xlsx|xls)$/i.test(file.name)
    if (!this.allowedTypes.includes(file.type) && !hasExcelExtension) {
      this.showError("نوع الملف غير مدعوم. يرجى اختيار ملف Excel (.xlsx أو .xls)")
      this.fileInput.value = ""
      return
    }

    if (file.size > this.maxFileSize) {
      this.showError("حجم الملف كبير جداً. الحد الأقصى المسموح: 50 ميجابايت")
      this.fileInput.value = ""
      return
    }

    this.clearFileErrors()
    this.displayFileInfo(file)
    this.enableUploadButton()
  }

  displayFileInfo(file) {
    this.fileName.textContent = file.name
    this.fileSize.textContent = this.formatFileSize(file.size)
    this.fileInfo.classList.remove("d-none")
    this.uploadZone.style.display = "none"
  }

  formatFileSize(bytes) {
    if (bytes === 0) return "0 بايت"

    const units = ["بايت", "كيلوبايت", "ميجابايت", "جيجابايت"]
    const unitIndex = Math.floor(Math.log(bytes) / Math.log(1024))
    return `${Number.parseFloat((bytes / Math.pow(1024, unitIndex)).toFixed(2))} ${units[unitIndex]}`
  }

  enableUploadButton() {
    this.uploadBtn.disabled = false
    this.uploadBtn.classList.remove("btn-success")
    this.uploadBtn.classList.add("btn-gradient-primary")
    this.btnText.textContent = "رفع وتحديث المخزون"
  }

  removeFile() {
    this.fileInput.value = ""
    this.fileInfo.classList.add("d-none")
    this.uploadZone.style.display = "block"
    this.progressContainer.classList.add("d-none")
    this.uploadBtn.disabled = true
    this.uploadBtn.classList.remove("btn-gradient-primary", "btn-success")
    this.btnText.textContent = "اختر ملف أولاً"
    this.stopProgress()
  }

  handleFormSubmit(event) {
    event.preventDefault()

    if (!this.fileInput.files[0]) {
      this.showError("يرجى اختيار ملف أولاً")
      return
    }

    this.startUpload(false)
  }

  startUpload(isConfirmed) {
    this.progressContainer.classList.remove("d-none")
    this.progressTitle.textContent = "جاري الرفع..."
    this.uploadBtn.disabled = true
    this.btnText.innerHTML = '<span class="loading-spinner me-2"></span>جاري الرفع...'

    const formData = new FormData()
    formData.append("excel_file", this.fileInput.files[0])
    if (isConfirmed) formData.append("confirm_old_file", "1")

    this.simulateProgress()

    fetch(window.location.href, {
      method: "POST",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
      },
      body: formData,
    })
      .then(async (response) => {
        const result = await response.json().catch(() => {
          throw new Error("استجابة غير متوقعة من الخادم")
        })
        return result
      })
      .then((result) => {
        if (result.status === "warning" && result.requires_confirmation) {
          this.prepareForRetry()
          this.showConfirmation(result.message)
          return
        }

        if (result.status === "success") {
          this.completeUpload()
          this.showAlert("success", result.message, false)
          return
        }

        if (result.status === "error" && result.reason === "old_file") {
          this.prepareForRetry()
          this.showRejection(result.message)
          return
        }

        this.handleUploadError(result.message || "تعذر رفع الملف")
      })
      .catch((error) => this.handleUploadError(error.message))
  }

  simulateProgress() {
    this.stopProgress()
    let progress = 0
    this.updateProgress(0)

    this.progressInterval = window.setInterval(() => {
      progress += Math.random() * 15

      if (progress >= 95) {
        progress = 95
        this.progressStatus.textContent = "جاري معالجة البيانات..."
        this.stopProgress()
        this.updateProgress(progress)
        return
      }

      this.updateProgress(progress)
      if (progress < 30) {
        this.progressStatus.textContent = "جاري رفع الملف..."
      } else if (progress < 60) {
        this.progressStatus.textContent = "جاري قراءة تاريخ وبيانات الملف..."
      } else {
        this.progressStatus.textContent = "جاري تحديث قاعدة البيانات..."
      }
    }, 200)
  }

  stopProgress() {
    if (this.progressInterval !== null) {
      window.clearInterval(this.progressInterval)
      this.progressInterval = null
    }
  }

  updateProgress(percent) {
    const roundedPercent = Math.round(percent)
    this.progressBar.style.width = `${roundedPercent}%`
    this.progressPercent.textContent = `${roundedPercent}%`
  }

  prepareForRetry() {
    this.stopProgress()
    this.progressContainer.classList.add("d-none")
    this.enableUploadButton()
  }

  completeUpload() {
    this.stopProgress()
    this.updateProgress(100)
    this.progressTitle.textContent = "تم رفع الملف بنجاح"
    this.progressStatus.textContent = "تم الانتهاء بنجاح!"
    this.progressContainer.classList.add("success-animation")
    this.btnText.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>تم بنجاح'
    this.uploadBtn.classList.remove("btn-gradient-primary")
    this.uploadBtn.classList.add("btn-success")
    this.uploadBtn.disabled = true
  }

  handleUploadError(message) {
    this.prepareForRetry()
    this.btnText.textContent = "إعادة المحاولة"
    this.showError(message)
  }

  showConfirmation(message) {
    this.confirmationPopup.classList.remove("is-rejected")
    this.confirmationTitle.textContent = "تنبيه: تاريخ الملف قديم"
    this.confirmationIcon.innerHTML = '<i class="bi bi-clock-history"></i>'
    this.confirmStaleUpload.classList.remove("d-none")
    this.cancelStaleUpload.textContent = "إلغاء"
    this.confirmationMessage.textContent = message
    this.confirmationPopup.classList.add("is-open")
    this.confirmationPopup.setAttribute("aria-hidden", "false")
    document.body.style.overflow = "hidden"
    this.confirmStaleUpload.focus()
  }

  showRejection(message) {
    this.showError(message)
    this.confirmationPopup.classList.add("is-rejected")
    this.confirmationTitle.textContent = "تم رفض الرفع"
    this.confirmationIcon.innerHTML = '<i class="bi bi-x-octagon-fill"></i>'
    this.confirmStaleUpload.classList.add("d-none")
    this.cancelStaleUpload.textContent = "حسناً"
    this.confirmationMessage.textContent = message
    this.confirmationPopup.classList.add("is-open")
    this.confirmationPopup.setAttribute("aria-hidden", "false")
    document.body.style.overflow = "hidden"
    this.cancelStaleUpload.focus()
  }

  hideConfirmation() {
    const wasRejected = this.confirmationPopup.classList.contains("is-rejected")
    this.confirmationPopup.classList.remove("is-open")
    this.confirmationPopup.setAttribute("aria-hidden", "true")
    document.body.style.overflow = ""
    if (wasRejected) {
      this.removeFile()
      this.uploadZone.focus()
    } else {
      this.uploadBtn.focus()
    }
  }

  showError(message) {
    this.clearFileErrors()
    this.showAlert("danger", message, false)
  }

  clearFileErrors() {
    document.querySelectorAll('[data-stock-upload-error]').forEach((alert) => alert.remove())
  }

  showAlert(type, message, autoDismiss) {
    const alertDiv = document.createElement("div")
    alertDiv.className = `alert alert-${type} alert-dismissible fade show shadow-sm`
    alertDiv.setAttribute("role", "alert")

    const icon = document.createElement("i")
    icon.className = `bi bi-${type === "success" ? "check-circle-fill" : "exclamation-triangle-fill"} me-2`
    alertDiv.appendChild(icon)
    alertDiv.appendChild(document.createTextNode(message))

    if (type === "danger") {
      alertDiv.setAttribute("data-stock-upload-error", "true")
      alertDiv.classList.remove("alert-dismissible")
    } else {
      const closeButton = document.createElement("button")
      closeButton.type = "button"
      closeButton.className = "btn-close"
      closeButton.setAttribute("aria-label", "إغلاق")
      closeButton.addEventListener("click", () => alertDiv.remove())
      alertDiv.appendChild(closeButton)
    }

    const container = document.querySelector(".container .row .col-lg-8")
    container.insertBefore(alertDiv, container.firstChild)

    if (autoDismiss) {
      window.setTimeout(() => {
        if (alertDiv.parentNode) alertDiv.remove()
      }, 7000)
    }
  }
}

document.addEventListener("DOMContentLoaded", () => new ExcelUploader())

document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
  anchor.addEventListener("click", function (event) {
    event.preventDefault()
    const target = document.querySelector(this.getAttribute("href"))
    if (target) target.scrollIntoView({ behavior: "smooth", block: "start" })
  })
})

window.addEventListener("beforeunload", () => {
  document.body.style.opacity = "0.7"
})
