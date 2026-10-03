const navToggle = document.querySelector(".nav-toggle");
const navMenu = document.querySelector(".navigation ul");

if (navToggle && navMenu) {
    const closeMenu = () => {
        navMenu.classList.remove("is-open");
        navToggle.setAttribute("aria-expanded", "false");
        navToggle.setAttribute("aria-label", "Abrir menú");
    };

    navToggle.addEventListener("click", () => {
        const isOpen = navMenu.classList.toggle("is-open");
        navToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
        navToggle.setAttribute("aria-label", isOpen ? "Cerrar menú" : "Abrir menú");
    });

    navMenu.querySelectorAll("a").forEach((link) => {
        link.addEventListener("click", closeMenu);
    });

    window.addEventListener("resize", () => {
        if (window.innerWidth > 850) {
            closeMenu();
        }
    });
}

const form = document.querySelector(".contact-form");
const statusEl = document.getElementById("form-status");
const messageCounter = document.getElementById("message-counter");
const startedAt = document.getElementById("started-at");
const fieldMap = {
    name: {
        input: document.getElementById("name"),
        error: document.getElementById("name-error"),
        validate: (value) => value.length >= 2 && value.length <= 80,
        message: "El nombre debe tener entre 2 y 80 caracteres."
    },
    email: {
        input: document.getElementById("email"),
        error: document.getElementById("email-error"),
        validate: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) && value.length <= 150,
        message: "Ingresa un correo electrónico válido."
    },
    message: {
        input: document.getElementById("message"),
        error: document.getElementById("message-error"),
        validate: (value) => value.length >= 10 && value.length <= 2000,
        message: "El mensaje debe tener entre 10 y 2000 caracteres."
    }
};
let statusTimeoutId;

function setStatus(message, type) {
    if (!statusEl) return;
    if (statusTimeoutId) {
        clearTimeout(statusTimeoutId);
    }

    statusEl.textContent = message;
    statusEl.classList.remove("is-success", "is-error");
    statusEl.classList.add(type === "success" ? "is-success" : "is-error");

    statusTimeoutId = setTimeout(() => {
        statusEl.textContent = "";
        statusEl.classList.remove("is-success", "is-error");
    }, 5000);
}

async function handleSubmit(event) {
    event.preventDefault();
    clearFieldErrors();
    const validation = validateForm();
    if (!validation.ok) {
        setStatus("Revisa los campos marcados y volvé a intentar.", "error");
        validation.firstInvalid?.focus();
        return;
    }

    const data = new FormData(event.target);
    const submitButton = form.querySelector("button[type='submit']");
    const defaultText = submitButton ? submitButton.textContent : "Enviar Mensaje";

    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = "Enviando...";
    }

    try {
        const response = await fetch(event.target.action, {
            method: form.method,
            body: data,
            headers: {
                Accept: "application/json"
            }
        });

        if (response.ok) {
            form.reset();
            setStatus("Mensaje enviado correctamente. Gracias por contactarme.", "success");
            return;
        }

        let payload = null;
        const contentType = response.headers.get("content-type") || "";
        if (contentType.includes("application/json")) {
            payload = await response.json();
        } else {
            const rawError = await response.text();
            if (rawError) {
                setStatus(`Error del servidor (${response.status}).`, "error");
                return;
            }
        }

        if (payload && payload.message) {
            applyServerErrors(payload);
            setStatus(payload.message, "error");
            return;
        }

        if (payload && Object.hasOwn(payload, "errors")) {
            setStatus(payload.errors.map((error) => error.message).join(", "), "error");
            return;
        }

        setStatus(`Hubo un problema al enviar el formulario (HTTP ${response.status}).`, "error");
    } catch (_error) {
        setStatus("No se pudo conectar con el servidor. Abre el sitio desde MAMP (localhost), no con file://.", "error");
    } finally {
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = defaultText;
        }
    }
}

if (form) {
    if (startedAt) {
        startedAt.value = String(Math.floor(Date.now() / 1000));
    }

    const messageInput = fieldMap.message.input;
    if (messageInput && messageCounter) {
        const updateCounter = () => {
            messageCounter.textContent = `${messageInput.value.length} / 2000`;
        };
        messageInput.addEventListener("input", updateCounter);
        updateCounter();
    }

    Object.values(fieldMap).forEach((field) => {
        if (!field.input) return;
        field.input.addEventListener("input", () => {
            validateField(field.input.name);
        });
        field.input.addEventListener("blur", () => {
            validateField(field.input.name);
        });
    });

    form.addEventListener("submit", handleSubmit);
}

function clearFieldErrors() {
    Object.values(fieldMap).forEach((field) => {
        if (field.input) field.input.classList.remove("is-invalid");
        if (field.error) field.error.textContent = "";
    });
}

function validateField(fieldName) {
    const field = fieldMap[fieldName];
    if (!field || !field.input) return true;

    const value = field.input.value.trim();
    const isValid = field.validate(value);
    if (!isValid) {
        field.input.classList.add("is-invalid");
        if (field.error) field.error.textContent = field.message;
        return false;
    }

    field.input.classList.remove("is-invalid");
    if (field.error) field.error.textContent = "";
    return true;
}

function validateForm() {
    let firstInvalid = null;
    let ok = true;

    Object.keys(fieldMap).forEach((key) => {
        const valid = validateField(key);
        if (!valid) {
            ok = false;
            if (!firstInvalid) {
                firstInvalid = fieldMap[key].input;
            }
        }
    });

    return { ok, firstInvalid };
}

function applyServerErrors(payload) {
    if (!payload || !Array.isArray(payload.errors)) return;
    payload.errors.forEach((error) => {
        const field = fieldMap[error.field];
        if (!field || !field.input) return;
        field.input.classList.add("is-invalid");
        if (field.error) field.error.textContent = error.message || field.message;
    });
}
