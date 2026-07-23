(function () {
  const textarea = document.getElementById("content");
  if (!textarea || typeof EasyMDE === "undefined") return;

  const uploadUrl = window.BLOG_UPLOAD_URL;
  const csrf = window.BLOG_CSRF;

  async function uploadFile(file, type) {
    const fd = new FormData();
    fd.append("file", file);
    fd.append("type", type || "image");
    fd.append("csrf_token", csrf);
    const res = await fetch(uploadUrl, { method: "POST", body: fd });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || "Upload failed");
    return data;
  }

  async function uploadDataUrl(dataUrl) {
    const fd = new FormData();
    fd.append("data_url", dataUrl);
    fd.append("type", "image");
    fd.append("csrf_token", csrf);
    const res = await fetch(uploadUrl, { method: "POST", body: fd });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || "Upload failed");
    return data;
  }

  function insertAtCursor(cm, text) {
    const doc = cm.getDoc();
    const cursor = doc.getCursor();
    doc.replaceRange(text, cursor);
  }

  function prismHighlight(code, lang) {
    if (typeof Prism === "undefined") return code;
    const language = (lang || "").toLowerCase();
    const grammar =
      (language && Prism.languages[language]) || Prism.languages.markup;
    try {
      return Prism.highlight(code, grammar, language || "markup");
    } catch (err) {
      return code;
    }
  }

  const easyMDE = new EasyMDE({
    element: textarea,
    spellChecker: false,
    nativeSpellcheck: true,
    inputStyle: "contenteditable",
    parsingConfig: {
      fencedCodeBlockHighlighting: true,
    },
    renderingConfig: {
      markedOptions: {
        highlight: prismHighlight,
      },
    },
    autosave: { enabled: false },
    placeholder: "Пишите в Markdown…",
    status: ["lines", "words", "cursor"],
    previewClass: ["editor-preview", "content"],
    toolbar: [
      "bold",
      "italic",
      "heading",
      "|",
      "quote",
      "unordered-list",
      "ordered-list",
      "|",
      "link",
      {
        name: "image-upload",
        action: function (editor) {
          const input = document.createElement("input");
          input.type = "file";
          input.accept = "image/jpeg,image/png,image/gif,image/webp";
          input.onchange = async function () {
            const file = input.files && input.files[0];
            if (!file) return;
            try {
              const data = await uploadFile(file, "image");
              const cm = editor.codemirror;
              insertAtCursor(cm, "\n![](" + data.url + ")\n");
            } catch (err) {
              alert(err.message || "Ошибка загрузки");
            }
          };
          input.click();
        },
        className: "fa fa-image",
        title: "Загрузить изображение",
      },
      {
        name: "file-upload",
        action: function (editor) {
          const input = document.createElement("input");
          input.type = "file";
          input.onchange = async function () {
            const file = input.files && input.files[0];
            if (!file) return;
            try {
              const data = await uploadFile(file, "file");
              const cm = editor.codemirror;
              const label = data.name || "файл";
              insertAtCursor(cm, "[" + label + "](" + data.url + ")");
            } catch (err) {
              alert(err.message || "Ошибка загрузки");
            }
          };
          input.click();
        },
        className: "fa fa-file",
        title: "Прикрепить файл",
      },
      "|",
      "code",
      {
        name: "code-block",
        action: function (editor) {
          const cm = editor.codemirror;
          insertAtCursor(cm, "\n```php\n// код\n```\n");
        },
        className: "fa fa-file-code-o",
        title: "Блок кода",
      },
      {
        name: "more",
        action: function (editor) {
          const cm = editor.codemirror;
          insertAtCursor(cm, "\n\n[more]\n\n");
        },
        className: "fa fa-ellipsis-h",
        title: "Разрыв «Читать далее»",
      },
      "|",
      "preview",
      "side-by-side",
      "fullscreen",
      "|",
      "guide",
    ],
  });

  (function enableNativeSpellcheck() {
    const cm = easyMDE.codemirror;
    const field = cm.getInputField();
    if (field) {
      field.setAttribute("spellcheck", "true");
      field.setAttribute("lang", "ru");
      field.setAttribute("autocorrect", "on");
      field.setAttribute("autocapitalize", "sentences");
    }
    cm.getWrapperElement().setAttribute("lang", "ru");
  })();

  // Ctrl+V / Cmd+V image paste
  easyMDE.codemirror.on("paste", async function (cm, event) {
    const items = (event.clipboardData && event.clipboardData.items) || [];
    for (let i = 0; i < items.length; i++) {
      const item = items[i];
      if (item.type && item.type.indexOf("image") === 0) {
        event.preventDefault();
        const file = item.getAsFile();
        if (!file) return;
        const reader = new FileReader();
        reader.onload = async function () {
          try {
            const data = await uploadDataUrl(String(reader.result));
            insertAtCursor(cm, "\n![](" + data.url + ")\n");
          } catch (err) {
            alert(err.message || "Ошибка вставки изображения");
          }
        };
        reader.readAsDataURL(file);
        return;
      }
    }
  });

  // Drag & drop images onto editor
  const wrapper = easyMDE.codemirror.getWrapperElement();
  wrapper.addEventListener("dragover", function (e) {
    e.preventDefault();
  });
  wrapper.addEventListener("drop", async function (e) {
    const files = e.dataTransfer && e.dataTransfer.files;
    if (!files || !files.length) return;
    const file = files[0];
    if (!file.type || file.type.indexOf("image") !== 0) return;
    e.preventDefault();
    e.stopPropagation();
    try {
      const data = await uploadFile(file, "image");
      insertAtCursor(easyMDE.codemirror, "\n![](" + data.url + ")\n");
    } catch (err) {
      alert(err.message || "Ошибка загрузки");
    }
  });
})();
