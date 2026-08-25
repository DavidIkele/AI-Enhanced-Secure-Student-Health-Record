/* ============================================
   Accessibility Widget - JavaScript
   ============================================ */
(function () {
  "use strict";

  /* ---- Inject CSS ---- */
  var cssUrl = (function () {
    var scripts = document.getElementsByTagName("script");
    for (var i = 0; i < scripts.length; i++) {
      if (scripts[i].src && scripts[i].src.indexOf("accessibility-widget.js") !== -1) {
        return scripts[i].src.replace(/\.js$/, ".css");
      }
    }
    return "accessibility-widget.css";
  })();
  var link = document.createElement("link");
  link.rel = "stylesheet";
  link.href = cssUrl;
  document.head.appendChild(link);

  /* ---- State ---- */
  var state = {
    panelOpen: false,
    kbOpen: false,
    ttsEnabled: false,
    ttsRate: 1,
    fontSize: 100,
    highContrast: "none", // none | dark | light
    focusHighlight: false,
    lineHeight: 150,
  };

  var activeElement = null;

  /* ===========================================
     BUILD DOM
     =========================================== */
  function buildToggle() {
    var btn = document.createElement("button");
    btn.id = "a11y-widget-toggle";
    btn.setAttribute("aria-label", "Open accessibility settings");
    btn.setAttribute("aria-expanded", "false");
    btn.innerHTML = "&#9881;"; // gear
    btn.addEventListener("click", togglePanel);
    document.body.appendChild(btn);
  }

  function buildPanel() {
    var panel = document.createElement("div");
    panel.id = "a11y-panel";
    panel.setAttribute("role", "dialog");
    panel.setAttribute("aria-label", "Accessibility settings");

    panel.innerHTML =
      '<div id="a11y-panel-header">' +
        '<span>Accessibility</span>' +
        '<button aria-label="Close accessibility panel">&times;</button>' +
      "</div>" +
      '<div id="a11y-panel-body">' +
        /* --- Font size --- */
        '<div class="a11y-section">' +
          '<h3>Text Size</h3>' +
          '<div class="a11y-slider-group">' +
            '<label for="a11y-font-slider">Size</label>' +
            '<input id="a11y-font-slider" type="range" min="80" max="200" value="100" step="10">' +
            '<span class="a11y-slider-val" id="a11y-font-val">100%</span>' +
          "</div>" +
        "</div>" +
        /* --- Line height --- */
        '<div class="a11y-section">' +
          '<h3>Line Spacing</h3>' +
          '<div class="a11y-slider-group">' +
            '<label for="a11y-lh-slider">Space</label>' +
            '<input id="a11y-lh-slider" type="range" min="100" max="300" value="150" step="10">' +
            '<span class="a11y-slider-val" id="a11y-lh-val">150%</span>' +
          "</div>" +
        "</div>" +
        /* --- High contrast --- */
        '<div class="a11y-section">' +
          '<h3>Contrast</h3>' +
          '<div class="a11y-btn-row">' +
            '<button class="a11y-btn active" data-contrast="none">Normal</button>' +
            '<button class="a11y-btn" data-contrast="dark">Dark</button>' +
            '<button class="a11y-btn" data-contrast="light">Light</button>' +
          "</div>" +
        "</div>" +
        /* --- Focus highlight --- */
        '<div class="a11y-section">' +
          '<h3>Keyboard Navigation</h3>' +
          '<div class="a11y-toggle-row">' +
            '<span>Highlight focused elements</span>' +
            '<button class="a11y-toggle" id="a11y-focus-toggle" role="switch" aria-checked="false"></button>' +
          "</div>" +
        "</div>" +
        /* --- Text to speech --- */
        '<div class="a11y-section">' +
          '<h3>Read Aloud (Text-to-Speech)</h3>' +
          '<div class="a11y-toggle-row">' +
            '<span>Click text to hear it</span>' +
            '<button class="a11y-toggle" id="a11y-tts-toggle" role="switch" aria-checked="false"></button>' +
          "</div>" +
          '<div class="a11y-slider-group" style="margin-top:8px">' +
            '<label for="a11y-tts-rate">Speed</label>' +
            '<input id="a11y-tts-rate" type="range" min="0.5" max="3" value="1" step="0.25">' +
            '<span class="a11y-slider-val" id="a11y-tts-rate-val">1x</span>' +
          "</div>" +
        "</div>" +
        /* --- On-screen keyboard --- */
        '<div class="a11y-section">' +
          '<h3>On-Screen Keyboard</h3>' +
          '<div class="a11y-btn-row">' +
            '<button class="a11y-btn" id="a11y-kb-toggle">Toggle Keyboard</button>' +
          "</div>" +
        "</div>" +
      "</div>";

    document.body.appendChild(panel);

    /* Close button */
    panel.querySelector("#a11y-panel-header button").addEventListener("click", togglePanel);

    /* Font slider */
    var fontSlider = panel.querySelector("#a11y-font-slider");
    fontSlider.addEventListener("input", function () {
      state.fontSize = parseInt(this.value, 10);
      panel.querySelector("#a11y-font-val").textContent = state.fontSize + "%";
      applyFontSize();
      saveState();
    });

    /* Line height slider */
    var lhSlider = panel.querySelector("#a11y-lh-slider");
    lhSlider.addEventListener("input", function () {
      state.lineHeight = parseInt(this.value, 10);
      panel.querySelector("#a11y-lh-val").textContent = state.lineHeight + "%";
      applyLineHeight();
      saveState();
    });

    /* Contrast buttons */
    var contrastBtns = panel.querySelectorAll("[data-contrast]");
    contrastBtns.forEach(function (btn) {
      btn.addEventListener("click", function () {
        contrastBtns.forEach(function (b) { b.classList.remove("active"); });
        btn.classList.add("active");
        state.highContrast = btn.getAttribute("data-contrast");
        applyContrast();
        saveState();
      });
    });

    /* Focus toggle */
    panel.querySelector("#a11y-focus-toggle").addEventListener("click", function () {
      state.focusHighlight = !state.focusHighlight;
      this.setAttribute("aria-checked", state.focusHighlight);
      applyFocusHighlight();
      saveState();
    });

    /* TTS toggle */
    panel.querySelector("#a11y-tts-toggle").addEventListener("click", function () {
      state.ttsEnabled = !state.ttsEnabled;
      this.setAttribute("aria-checked", state.ttsEnabled);
      saveState();
    });

    /* TTS rate */
    var ttsRate = panel.querySelector("#a11y-tts-rate");
    ttsRate.addEventListener("input", function () {
      state.ttsRate = parseFloat(this.value);
      panel.querySelector("#a11y-tts-rate-val").textContent = state.ttsRate + "x";
      saveState();
    });

    /* On-screen keyboard */
    panel.querySelector("#a11y-kb-toggle").addEventListener("click", toggleKeyboard);
  }

  /* ===========================================
     ON-SCREEN KEYBOARD
     =========================================== */
  var kbLayout = [
    ["1","2","3","4","5","6","7","8","9","0"],
    ["q","w","e","r","t","y","u","i","o","p"],
    ["a","s","d","f","g","h","j","k","l"],
    ["z","x","c","v","b","n","m"],
    ["space","backspace","enter"]
  ];

  function buildKeyboard() {
    var kb = document.createElement("div");
    kb.id = "a11y-onscreen-kb";
    kb.setAttribute("role", "application");
    kb.setAttribute("aria-label", "On-screen keyboard");

    kbLayout.forEach(function (row) {
      row.forEach(function (key) {
        var btn = document.createElement("button");
        btn.type = "button";
        btn.setAttribute("tabindex", "-1");

        if (key === "space") {
          btn.textContent = "Space";
          btn.className = "a11y-kb-space";
        } else if (key === "backspace") {
          btn.textContent = "Backspace";
          btn.className = "a11y-kb-backspace";
        } else if (key === "enter") {
          btn.textContent = "Enter";
          btn.className = "a11y-kb-enter";
        } else {
          btn.textContent = key;
        }

        btn.addEventListener("click", function () {
          handleKeyInput(key);
        });

        kb.appendChild(btn);
      });
    });

    document.body.appendChild(kb);
  }

  function handleKeyInput(key) {
    var el = document.activeElement;
    if (!el || el === document.body || el.id === "a11y-widget-toggle") {
      el = activeElement;
    }
    if (!el) return;

    var tagName = el.tagName.toLowerCase();
    var isInput = tagName === "input" || tagName === "textarea" || el.isContentEditable;

    if (!isInput) return;

    if (key === "backspace") {
      el.dispatchEvent(new KeyboardEvent("keydown", { key: "Backspace", bubbles: true }));
      if (tagName === "input" || tagName === "textarea") {
        var start = el.selectionStart;
        var end = el.selectionEnd;
        if (start !== end) {
          el.value = el.value.slice(0, start) + el.value.slice(end);
          el.selectionStart = el.selectionEnd = start;
        } else if (start > 0) {
          el.value = el.value.slice(0, start - 1) + el.value.slice(start);
          el.selectionStart = el.selectionEnd = start - 1;
        }
        el.dispatchEvent(new InputEvent("input", { bubbles: true }));
      }
    } else if (key === "enter") {
      el.dispatchEvent(new KeyboardEvent("keydown", { key: "Enter", bubbles: true }));
    } else if (key === "space") {
      if (tagName === "input" || tagName === "textarea") {
        var s = el.selectionStart;
        el.value = el.value.slice(0, s) + " " + el.value.slice(el.selectionEnd);
        el.selectionStart = el.selectionEnd = s + 1;
        el.dispatchEvent(new InputEvent("input", { bubbles: true }));
      }
    } else {
      el.dispatchEvent(new KeyboardEvent("keydown", { key: key, bubbles: true }));
      if (tagName === "input" || tagName === "textarea") {
        var pos = el.selectionStart;
        el.value = el.value.slice(0, pos) + key + el.value.slice(el.selectionEnd);
        el.selectionStart = el.selectionEnd = pos + 1;
        el.dispatchEvent(new InputEvent("input", { bubbles: true }));
      }
    }
  }

  /* ===========================================
     TEXT-TO-SPEECH
     =========================================== */
  function setupTTS() {
    document.addEventListener("click", function (e) {
      if (!state.ttsEnabled) return;

      var target = e.target;
      if (
        target.id === "a11y-widget-toggle" ||
        target.closest("#a11y-panel") ||
        target.closest("#a11y-onscreen-kb")
      ) {
        return;
      }

      var text = target.textContent.trim();
      if (!text) return;

      window.speechSynthesis.cancel();

      /* Remove old highlight */
      var old = document.querySelector(".a11y-tts-highlight");
      if (old) old.classList.remove("a11y-tts-highlight");

      target.classList.add("a11y-tts-highlight");

      var utter = new SpeechSynthesisUtterance(text);
      utter.rate = state.ttsRate;
      utter.onend = function () {
        target.classList.remove("a11y-tts-highlight");
      };

      window.speechSynthesis.speak(utter);
    });
  }

  /* ===========================================
     APPLY SETTINGS
     =========================================== */
  function applyFontSize() {
    document.documentElement.style.fontSize = state.fontSize + "%";
  }

  function applyLineHeight() {
    document.body.style.lineHeight = state.lineHeight / 100;
  }

  function applyContrast() {
    document.body.classList.remove("a11y-hc-dark", "a11y-hc-light");
    if (state.highContrast === "dark") {
      document.body.classList.add("a11y-hc-dark");
    } else if (state.highContrast === "light") {
      document.body.classList.add("a11y-hc-light");
    }
  }

  function applyFocusHighlight() {
    document.body.classList.toggle("a11y-focus-visible", state.focusHighlight);
  }

  function applyAll() {
    applyFontSize();
    applyLineHeight();
    applyContrast();
    applyFocusHighlight();

    /* Restore panel controls */
    var panel = document.getElementById("a11y-panel");
    if (panel) {
      panel.querySelector("#a11y-font-slider").value = state.fontSize;
      panel.querySelector("#a11y-font-val").textContent = state.fontSize + "%";
      panel.querySelector("#a11y-lh-slider").value = state.lineHeight;
      panel.querySelector("#a11y-lh-val").textContent = state.lineHeight + "%";

      var contrastBtns = panel.querySelectorAll("[data-contrast]");
      contrastBtns.forEach(function (b) {
        b.classList.toggle("active", b.getAttribute("data-contrast") === state.highContrast);
      });

      panel.querySelector("#a11y-focus-toggle").setAttribute("aria-checked", state.focusHighlight);
      panel.querySelector("#a11y-tts-toggle").setAttribute("aria-checked", state.ttsEnabled);
      panel.querySelector("#a11y-tts-rate").value = state.ttsRate;
      panel.querySelector("#a11y-tts-rate-val").textContent = state.ttsRate + "x";
    }
  }

  /* ===========================================
     PERSISTENCE (localStorage)
     =========================================== */
  function saveState() {
    try {
      localStorage.setItem("a11y-widget-state", JSON.stringify(state));
    } catch (e) { /* ignore */ }
  }

  function loadState() {
    try {
      var raw = localStorage.getItem("a11y-widget-state");
      if (raw) {
        var saved = JSON.parse(raw);
        Object.keys(state).forEach(function (k) {
          if (saved[k] !== undefined) state[k] = saved[k];
        });
      }
    } catch (e) { /* ignore */ }
  }

  /* ===========================================
     TOGGLES
     =========================================== */
  function togglePanel() {
    state.panelOpen = !state.panelOpen;
    var panel = document.getElementById("a11y-panel");
    var toggle = document.getElementById("a11y-widget-toggle");
    panel.classList.toggle("open", state.panelOpen);
    toggle.setAttribute("aria-expanded", state.panelOpen);

    if (state.panelOpen) {
      panel.querySelector("#a11y-panel-header button").focus();
    }
  }

  function toggleKeyboard() {
    state.kbOpen = !state.kbOpen;
    document.getElementById("a11y-onscreen-kb").classList.toggle("open", state.kbOpen);
    if (!state.kbOpen) {
      document.documentElement.style.paddingBottom = "";
    } else {
      document.documentElement.style.paddingBottom = "110px";
    }
  }

  /* Track active element for on-screen keyboard */
  document.addEventListener("focusin", function (e) {
    activeElement = e.target;
  });

  /* Escape key closes panel & keyboard */
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      if (state.kbOpen) toggleKeyboard();
      else if (state.panelOpen) togglePanel();
    }
  });

  /* ===========================================
     INIT
     =========================================== */
  function init() {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", init);
      return;
    }

    loadState();
    buildToggle();
    buildPanel();
    buildKeyboard();
    applyAll();
    setupTTS();
  }

  init();
})();
