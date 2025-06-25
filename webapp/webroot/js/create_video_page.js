window.App = {
    dom: {
        uploadForm: null,
        fileInput: null,
        uploadBtn: null,
        loadingIndicator: null
    },
    state: {
        uploading: false
    },
    createPreSignedUrl: "/videos/pre-signed-url",
    createVideoUrl: "/videos/create"
}

const init = function init(event) {

    App.dom.uploadForm = document.getElementById("upload-video-form")
    App.dom.fileInput = App.dom.uploadForm.querySelector("input[type='file']")
    App.dom.uploadBtn = App.dom.uploadForm.querySelector("input[type='submit']")
    App.dom.loadingIndicator = document.getElementById("loading-indicator")

    App.dom.fileInput.addEventListener("change", function (event) {
        const file = event.target.files[0];
        if (file && file.size > 3e+9) { // 3 GB
            alert(`The file has ${returnFileSize(file.size)}. It cannot be greater than ${returnFileSize(3e+9)}`)
            event.target.value = "";
        }
    })

    App.dom.uploadForm.addEventListener("submit", async function (event) {
        event.preventDefault()
        const { loadingIndicator } = App.dom
        let preSignedUrl = undefined
        let key = undefined
        const file = App.dom.fileInput.files[0]
        if (!file) {
            return
        }
        setUploading(true)
        await axios.post(App.createPreSignedUrl, {
            mimeType: file.type
        }).then(function (response) {
            preSignedUrl = response.data.url
            key = response.data.key
        }).catch(function (error) {
            setUploading(false)
        })
        if (!preSignedUrl) {
            throw new Error("Could not fetch pre-signed url")
        }
        await axios.put(preSignedUrl, file, { //Pass file directly, not FormData as Blob
            headers: {
                'Content-Type': 'video/mp4'
            },
            timeout: 0,
            onUploadProgress: function (progressEvent) {
                const percent = ((progressEvent.loaded * 100) / progressEvent.total).toFixed(2)
                if (percent < 100) {
                    loadingIndicator.innerText = `Uploading: ${percent} %`
                } else if (percent == 100) {
                    loadingIndicator.innerText = `Uploading: ${percent} %`
                    loadingIndicator.innerText = `Almost there...`
                }
            },
        })

        await axios.post(App.createVideoUrl, {
            "key": key
        }).finally(function () {
            loadingIndicator.innerText = `Upload complete`
            setUploading(false)
        })
    })

    function returnFileSize(number) {
        if (number < 1e3) {
            return `${number} bytes`;
        } else if (number >= 1e3 && number < 1e6) {
            return `${(number / 1e3).toFixed(1)} KB`;
        }
        return `${(number / 1e6).toFixed(1)} MB`;
    }

    function setUploading(uploading) {
        if (uploading) {
            App.state.uploading = true
            App.dom.uploadBtn.setAttribute("disabled", "")
        } else {
            App.state.uploading = false
            App.dom.uploadBtn.removeAttribute("disabled")
        }
    }
}

window.addEventListener("beforeunload", function (event) {
    if (App.state.uploading) {
        event.preventDefault();
    }
})

document.addEventListener("DOMContentLoaded", init)