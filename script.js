// Goldman Seed Enterprise

async function getCustomerCsrfToken() {
    const response = await fetch("auth_status.php", {
        headers: { Accept: "application/json" }
    });
    const result = await response.json();
    if (!response.ok || typeof result.csrf_token !== "string") {
        throw new Error(result.error || "Account service is unavailable. Try again later.");
    }
    return result.csrf_token;
}

async function submitCustomerRequest(endpoint, data) {
    const csrfToken = await getCustomerCsrfToken();
    const response = await fetch(endpoint, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": csrfToken
        },
        body: JSON.stringify(data)
    });
    const result = await response.json();
    if (!response.ok) {
        throw new Error(result.error || "The request could not be completed.");
    }
    return result;
}


// ===================================
// 1. SIGN IN + SHOW/HIDE PASSWORD
// ===================================

const signInForm = document.getElementById("signInForm");

if (signInForm) {
    const passwordInput = document.getElementById("password");
    const togglePassword = document.getElementById("togglePassword");
    const loginMessage = document.getElementById("loginMessage");
    const identifierInput = document.getElementById("loginIdentifier");
    const identifierError = document.getElementById("loginIdentifierError");
    const passwordError = document.getElementById("loginPasswordError");

    function clearSignInErrors() {
        identifierError.textContent = "";
        passwordError.textContent = "";
        identifierInput.removeAttribute("aria-invalid");
        passwordInput.removeAttribute("aria-invalid");
        loginMessage.textContent = "";
        loginMessage.classList.remove("is-error");
    }

    function showSignInFieldError(input, errorElement, message) {
        errorElement.textContent = message;
        input.setAttribute("aria-invalid", "true");
    }

    function reloadWithInvalidCredentials() {
        const nextUrl = new URL(window.location.href);
        nextUrl.searchParams.set("login_error", "invalid");
        window.location.replace(nextUrl.pathname + nextUrl.search);
    }

    const signInQuery = new URLSearchParams(window.location.search);
    if (signInQuery.get("login_error") === "invalid") {
        loginMessage.textContent = "The email or password is incorrect.";
        loginMessage.classList.add("is-error");
    }

    [identifierInput, passwordInput].forEach(function (input) {
        input.addEventListener("input", function () {
            const errorElement = input === identifierInput ? identifierError : passwordError;
            errorElement.textContent = "";
            input.removeAttribute("aria-invalid");
            loginMessage.textContent = "";
            loginMessage.classList.remove("is-error");

            if (signInQuery.has("login_error")) {
                signInQuery.delete("login_error");
                const query = signInQuery.toString();
                const nextUrl = window.location.pathname + (query ? `?${query}` : "");
                window.history.replaceState(null, "", nextUrl);
            }
        });
    });

    // SHOW / HIDE PASSWORD
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener("click", function () {
            const showPassword = passwordInput.type === "password";

            passwordInput.type = showPassword ? "text" : "password";
            togglePassword.textContent = showPassword ? "Hide" : "Show";

            togglePassword.setAttribute(
                "aria-label",
                showPassword ? "Hide password" : "Show password"
            );
        });
    }

    // SIGN-IN FORM
    signInForm.addEventListener("submit", async function (event) {
        event.preventDefault();
        clearSignInErrors();

        const email = identifierInput.value.trim();
        const accountPassword = passwordInput.value;

        if (!email) {
            showSignInFieldError(identifierInput, identifierError, "Required");
        } else if (identifierInput.validity.typeMismatch) {
            showSignInFieldError(identifierInput, identifierError, "Enter a valid email");
        }

        if (!accountPassword) {
            showSignInFieldError(passwordInput, passwordError, "Required");
        } else if (accountPassword.length < 12 || accountPassword.length > 72) {
            reloadWithInvalidCredentials();
            return;
        }

        const firstInvalidField = [identifierInput, passwordInput].find(function (input) {
            return input.getAttribute("aria-invalid") === "true";
        });
        if (firstInvalidField) {
            firstInvalidField.focus();
            return;
        }

        const submitButton = signInForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        try {
            const result = await submitCustomerRequest("account_login.php", {
                email,
                password: accountPassword,
                return_to: new URLSearchParams(window.location.search).get("return")
            });
            window.location.href = result.redirect;
        } catch (error) {
            if (error.message === "The email or password is incorrect.") {
                reloadWithInvalidCredentials();
                return;
            }
            loginMessage.textContent = error.message;
            loginMessage.classList.add("is-error");
        } finally {
            submitButton.disabled = false;
        }
    });
}


// ===================================
// 2. REGISTER PAGE
// ===================================

const registerForm = document.getElementById("registerForm");

if (registerForm) {
    const fullNameInput = document.getElementById("fullName");
    const emailInput = document.getElementById("registerEmail");
    const phoneInput = document.getElementById("registerPhone");
    const passwordInput = document.getElementById("registerPassword");
    const confirmPasswordInput = document.getElementById("confirmPassword");
    const termsCheckbox = document.getElementById("agreeTerms");
    const registerMessage = document.getElementById("registerMessage");
    const registerFields = [
        {
            input: fullNameInput,
            error: document.getElementById("fullNameError")
        },
        {
            input: emailInput,
            error: document.getElementById("registerEmailError")
        },
        {
            input: phoneInput,
            error: document.getElementById("registerPhoneError")
        },
        {
            input: passwordInput,
            error: document.getElementById("registerPasswordError")
        },
        {
            input: confirmPasswordInput,
            error: document.getElementById("confirmPasswordError")
        },
        {
            input: termsCheckbox,
            error: document.getElementById("agreeTermsError")
        }
    ];

    function clearRegisterErrors() {
        registerFields.forEach(function (field) {
            field.error.textContent = "";
            field.input.removeAttribute("aria-invalid");
        });
        registerMessage.textContent = "";
        registerMessage.classList.remove("is-error");
    }

    function showFieldError(input, message) {
        const field = registerFields.find(function (entry) {
            return entry.input === input;
        });
        field.error.textContent = message;
        input.setAttribute("aria-invalid", "true");
    }

    function showRegisterError(message) {
        registerMessage.textContent = message;
        registerMessage.classList.add("is-error");
    }

    registerForm.addEventListener("submit", async function (event) {
        event.preventDefault();

        const fullName = fullNameInput.value.trim();
        const email = emailInput.value.trim();
        const phoneNo = phoneInput.value.trim();
        const customerPassword = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        clearRegisterErrors();

        if (!fullName) {
            showFieldError(fullNameInput, "Required");
        }

        if (!email) {
            showFieldError(emailInput, "Required");
        } else if (emailInput.validity.typeMismatch) {
            showFieldError(emailInput, "Enter a valid email");
        }

        if (!phoneNo || !/^[0-9+().\-\s]+$/.test(phoneNo) || phoneNo.length > 30) {
            showFieldError(phoneInput, "Enter a valid phone number");
        }

        if (customerPassword.length < 12 || customerPassword.length > 72) {
            showFieldError(passwordInput, "Use 12–72 characters");
        }

        if (!confirmPassword) {
            showFieldError(confirmPasswordInput, "Required");
        } else if (customerPassword !== confirmPassword) {
            showFieldError(confirmPasswordInput, "Passwords do not match");
        }

        if (!termsCheckbox.checked) {
            showFieldError(termsCheckbox, "Required");
        }

        const firstInvalidField = registerFields.find(function (field) {
            return field.error.textContent !== "";
        });
        if (firstInvalidField) {
            firstInvalidField.input.focus();
            return;
        }

        const submitButton = registerForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        try {
            await submitCustomerRequest("customer_register.php", {
                full_name: fullName,
                email,
                phone_no: phoneNo,
                password: customerPassword
            });
            window.location.href =
                new URLSearchParams(window.location.search).get("return") === "product"
                    ? "product.html"
                    : "home.html";
        } catch (error) {
            if (error.message === "An account with this email already exists. Sign in instead.") {
                showFieldError(emailInput, error.message);
                emailInput.focus();
            } else {
                showRegisterError(error.message);
            }
        } finally {
            submitButton.disabled = false;
        }
    });

    registerFields.forEach(function (field) {
        field.input.addEventListener("input", function () {
            field.error.textContent = "";
            field.input.removeAttribute("aria-invalid");
            registerMessage.textContent = "";
            registerMessage.classList.remove("is-error");
        });
    });
}


// ===================================
// 3. SIGN OUT
// ===================================

const confirmSignOut = document.getElementById("confirmSignOut");

if (confirmSignOut) {
    confirmSignOut.addEventListener("click", async function () {
        confirmSignOut.disabled = true;
        try {
            await submitCustomerRequest("logout.php", {});
            window.location.href = "home.html";
        } catch (error) {
            const message = document.querySelector(".signout-card .subheading");
            if (message) {
                message.textContent = error.message;
            }
            confirmSignOut.disabled = false;
        }
    });
}


// ===================================
// 4. ACCOUNT NAVIGATION
//    HOME + CONTACT PAGES
// ===================================

const isHomeOrContact =
    document.body.classList.contains("home-page") ||
    document.body.classList.contains("contact-page");

if (isHomeOrContact) {
    const signInLink =
        document.getElementById("homeSignInLink");

    const registerLink =
        document.getElementById("homeRegisterLink");

    const signOutLink =
        document.getElementById("homeSignOutLink");
    const profileLink =
        document.getElementById("homeProfileLink");
    const profileAvatar =
        document.getElementById("homeProfileAvatar");

    if (signInLink && registerLink && signOutLink) {
        fetch("auth_status.php", {
            headers: { Accept: "application/json" }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(`Account status request failed (${response.status}).`);
                }
                return response.json();
            })
            .then(function (account) {
                signInLink.hidden = account.authenticated === true;
                registerLink.hidden = account.authenticated === true;
                signOutLink.hidden = account.authenticated !== true;
                if (profileLink) {
                    profileLink.hidden = account.authenticated !== true;
                }
                if (profileAvatar && typeof account.avatar_url === "string") {
                    profileAvatar.src = account.avatar_url;
                }
            })
            .catch(function (error) {
                signInLink.hidden = false;
                registerLink.hidden = false;
                signOutLink.hidden = true;
                if (profileLink) {
                    profileLink.hidden = true;
                }
                console.error("Unable to check customer sign-in status:", error);
            });
    }
}


// ===================================
// 5. ACTIVE NAVIGATION LINK
// ===================================

const currentPage =
    window.location.pathname.split("/").pop() || "home.html";

const navLinks = document.querySelectorAll(".site-nav a");

navLinks.forEach(function (link) {
    const linkPage = link.getAttribute("href").split("#")[0];

    // Ignore links that only point to a section on the same page.
    if (!linkPage) {
        return;
    }

    if (linkPage === currentPage) {
        link.classList.add("active");
        link.setAttribute("aria-current", "page");
    } else {
        link.classList.remove("active");
        link.removeAttribute("aria-current");
    }
});

// ===================================
// 6. BACK BUTTON
//    SIGN IN + REGISTER
// ===================================

const backButton = document.getElementById("backButton");

if (backButton) {
    backButton.addEventListener("click", function () {
        const previousPage = document.referrer
            ? new URL(document.referrer)
            : null;
        if (previousPage && previousPage.origin === window.location.origin) {
            window.history.back();
        } else {
            window.location.href = "home.html";
        }
    });
}


// ===================================
// 7. CONTACT FORM
// ===================================

const contactForm = document.getElementById("contactForm");

if (contactForm) {
    const contactFeedback = document.getElementById("contactFeedback");
    const contactFields = [
        {
            input: document.getElementById("contactName"),
            error: document.getElementById("contactNameError"),
            validate(input) {
                if (!input.value.trim()) {
                    return "Enter your name.";
                }
                if (input.validity.tooLong) {
                    return "Your name must be 120 characters or fewer.";
                }
                return "";
            }
        },
        {
            input: document.getElementById("contactEmail"),
            error: document.getElementById("contactEmailError"),
            validate(input) {
                if (!input.value.trim()) {
                    return "Enter your email address.";
                }
                if (input.validity.typeMismatch) {
                    return "Enter a valid email address.";
                }
                if (input.validity.tooLong) {
                    return "Your email address must be 254 characters or fewer.";
                }
                return "";
            }
        },
        {
            input: document.getElementById("contactMessage"),
            error: document.getElementById("contactMessageError"),
            validate(input) {
                if (!input.value.trim()) {
                    return "Enter a message.";
                }
                if (input.validity.tooLong) {
                    return "Your message must be 5,000 characters or fewer.";
                }
                return "";
            }
        }
    ];

    function validateContactField(field) {
        const message = field.validate(field.input);
        field.error.textContent = message;
        if (message) {
            field.input.setAttribute("aria-invalid", "true");
        } else {
            field.input.removeAttribute("aria-invalid");
        }
        return !message;
    }

    contactFields.forEach(function (field) {
        field.input.addEventListener("input", function () {
            validateContactField(field);
            contactFeedback.textContent = "";
            contactFeedback.classList.remove("is-error");
        });
    });

    contactForm.addEventListener("submit", async function (event) {
        event.preventDefault();

        const invalidFields = contactFields.filter(function (field) {
            return !validateContactField(field);
        });
        if (invalidFields.length > 0) {
            contactFeedback.textContent = "Please correct the highlighted fields.";
            contactFeedback.classList.add("is-error");
            invalidFields[0].input.focus();
            return;
        }

        const formData = new FormData(contactForm);
        const submitButton = contactForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        contactFeedback.classList.remove("is-error");
        contactFeedback.textContent = "Sending your message...";

        try {
            await submitCustomerRequest("contact_message.php", {
                name: formData.get("name").trim(),
                email: formData.get("email").trim(),
                message: formData.get("message").trim()
            });
            contactFeedback.textContent = "Thank you. Your message has been sent.";
            contactForm.reset();
            contactFields.forEach(function (field) {
                field.error.textContent = "";
                field.input.removeAttribute("aria-invalid");
            });
        } catch (error) {
            contactFeedback.textContent = error.message;
            contactFeedback.classList.add("is-error");
        } finally {
            submitButton.disabled = false;
        }
    });
}

const profileForm = document.getElementById("profileForm");

if (profileForm) {
    const fullNameInput = document.getElementById("profileFullName");
    const emailInput = document.getElementById("profileEmail");
    const phoneInput = document.getElementById("profilePhone");
    const avatarOptions = document.getElementById("profileAvatarOptions");
    const profileMessage = document.getElementById("profileMessage");
    const fullNameError = document.getElementById("profileFullNameError");
    const phoneError = document.getElementById("profilePhoneError");
    const avatarError = document.getElementById("profileAvatarError");
    const saveProfileButton = document.getElementById("saveProfileButton");
    let profileCsrfToken = "";

    function showProfileMessage(message, isError) {
        profileMessage.textContent = message;
        profileMessage.classList.toggle("is-error", isError);
    }

    function renderProfileAvatars(options, selectedAvatar) {
        avatarOptions.replaceChildren();
        Object.entries(options).forEach(function ([avatar, imagePath], index) {
            const label = document.createElement("label");
            label.className = "profile-avatar-option";

            const radio = document.createElement("input");
            radio.type = "radio";
            radio.name = "avatar";
            radio.value = avatar;
            radio.required = true;
            radio.checked = avatar === selectedAvatar;
            radio.id = `profileAvatar${index}`;
            radio.addEventListener("change", function () {
                avatarError.textContent = "";
            });

            const image = document.createElement("img");
            image.src = imagePath;
            image.alt = "";
            image.loading = "lazy";

            const name = document.createElement("span");
            name.textContent = avatar
                .split("-")
                .map(function (part) {
                    return part.charAt(0).toUpperCase() + part.slice(1);
                })
                .join(" ");

            label.append(radio, image, name);
            avatarOptions.appendChild(label);
        });
    }

    async function loadProfile() {
        saveProfileButton.disabled = true;
        showProfileMessage("Loading your profile...", false);

        try {
            const response = await fetch("profile_api.php", {
                headers: { Accept: "application/json" }
            });
            const profile = await response.json();
            if (response.status === 401) {
                window.location.replace("signin.html?return=profile");
                return;
            }
            if (!response.ok) {
                throw new Error(profile.error || "Your profile could not be loaded.");
            }
            if (typeof profile.full_name !== "string" ||
                typeof profile.email !== "string" ||
                typeof profile.phone_no !== "string" ||
                typeof profile.avatar !== "string" ||
                typeof profile.csrf_token !== "string" ||
                !profile.avatar_options ||
                typeof profile.avatar_options !== "object") {
                throw new Error("The profile service returned invalid account details.");
            }

            fullNameInput.value = profile.full_name;
            emailInput.value = profile.email;
            phoneInput.value = profile.phone_no;
            profileCsrfToken = profile.csrf_token;
            renderProfileAvatars(profile.avatar_options, profile.avatar);
            showProfileMessage("", false);
            saveProfileButton.disabled = false;
        } catch (error) {
            showProfileMessage(error.message || "Your profile could not be loaded.", true);
            console.error("Unable to load account profile:", error);
        }
    }

    profileForm.addEventListener("submit", async function (event) {
        event.preventDefault();
        fullNameError.textContent = "";
        phoneError.textContent = "";
        avatarError.textContent = "";

        const fullName = fullNameInput.value.trim();
        const phoneNo = phoneInput.value.trim();
        const avatar = profileForm.querySelector('input[name="avatar"]:checked');
        let isValid = true;

        if (!fullName || fullName.length > 120) {
            fullNameError.textContent = "Enter a name of 1–120 characters.";
            isValid = false;
        }
        if (phoneNo.length > 30 || (phoneNo && !/^[0-9+().\-\s]+$/.test(phoneNo))) {
            phoneError.textContent = "Enter a valid phone number of up to 30 characters.";
            isValid = false;
        }
        if (!avatar) {
            avatarError.textContent = "Choose one of the profile pictures.";
            isValid = false;
        }
        if (!isValid) {
            showProfileMessage("Please correct the highlighted fields.", true);
            return;
        }

        saveProfileButton.disabled = true;
        showProfileMessage("Saving your changes...", false);
        try {
            const response = await fetch("profile_api.php", {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-Token": profileCsrfToken
                },
                body: JSON.stringify({
                    full_name: fullName,
                    phone_no: phoneNo,
                    avatar: avatar.value
                })
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.error || "Your profile could not be saved.");
            }

            const headerAvatar = document.getElementById("homeProfileAvatar");
            if (headerAvatar && typeof result.avatar_url === "string") {
                headerAvatar.src = result.avatar_url;
            }
            showProfileMessage("Your profile has been updated.", false);
        } catch (error) {
            showProfileMessage(error.message || "Your profile could not be saved.", true);
            console.error("Unable to save account profile:", error);
        } finally {
            saveProfileButton.disabled = false;
        }
    });

    [fullNameInput, phoneInput].forEach(function (input) {
        input.addEventListener("input", function () {
            if (input === fullNameInput) {
                fullNameError.textContent = "";
            } else {
                phoneError.textContent = "";
            }
            showProfileMessage("", false);
        });
    });

    loadProfile();
}

const adminContactInbox = document.getElementById("adminContactInbox");

if (adminContactInbox) {
    const contactMessagesList = document.getElementById("contactMessagesList");
    const contactMessagesStatus = document.getElementById("contactMessagesStatus");
    const refreshContactMessagesButton = document.getElementById("refreshContactMessages");

    async function loadContactMessages() {
        contactMessagesStatus.textContent = "Loading customer messages...";
        contactMessagesList.replaceChildren();

        try {
            const response = await fetch("admin_contact_messages.php", {
                headers: { Accept: "application/json" }
            });
            const messages = await response.json();
            if (!response.ok || !Array.isArray(messages)) {
                throw new Error(messages.error || "Customer messages could not be loaded.");
            }

            if (messages.length === 0) {
                contactMessagesStatus.textContent = "There are no customer messages yet.";
                return;
            }

            messages.forEach(function (message) {
                const card = document.createElement("article");
                card.className = "admin-record-card";

                const name = document.createElement("h3");
                name.textContent = message.name;

                const meta = document.createElement("p");
                meta.className = "admin-record-meta";
                meta.textContent = `${message.email} · ${message.created_at}`;

                const content = document.createElement("p");
                content.className = "admin-record-message";
                content.textContent = message.message;

                card.append(name, meta, content);
                contactMessagesList.appendChild(card);
            });
            contactMessagesStatus.textContent = `${messages.length} customer message${messages.length === 1 ? "" : "s"}.`;
        } catch (error) {
            contactMessagesStatus.textContent = error.message || "Customer messages could not be loaded.";
            console.error("Unable to load customer messages:", error);
        }
    }

    refreshContactMessagesButton.addEventListener("click", loadContactMessages);

    fetch("auth_status.php", {
        headers: { Accept: "application/json" }
    })
        .then(async function (response) {
            const account = await response.json();
            if (!response.ok) {
                throw new Error(account.error || "Administrator status could not be checked.");
            }
            return account;
        })
        .then(function (account) {
            if (account.authenticated === true && account.role === "admin") {
                adminContactInbox.hidden = false;
                loadContactMessages();
            }
        })
        .catch(function (error) {
            console.error("Unable to check contact inbox access:", error);
        });
}


// ===================================
// 8. PRODUCT CATALOGUE AND MANAGEMENT
// ===================================

const productForm = document.getElementById("productForm");

if (productForm) {
    const productList = document.getElementById("productList");
    const shopProductList = document.getElementById("shopProductList");
    const productMessage = document.getElementById("productMessage");
    const shopStatus = document.getElementById("shopStatus");
    const productName = document.getElementById("productName");
    const productCategory = document.getElementById("productCategory");
    const productDescription = document.getElementById("productDescription");
    const productPrice = document.getElementById("productPrice");
    const productUnit = document.getElementById("productUnit");
    const productImage = document.getElementById("productImage");
    const productImageDropzone = document.getElementById("productImageDropzone");
    const productImagePrompt = document.getElementById("productImagePrompt");
    const productImagePreview = document.getElementById("productImagePreview");
    const productImageStatus = document.getElementById("productImageStatus");
    const removeProductImageButton = document.getElementById("removeProductImage");
    const productImageCropDialog = document.getElementById("productImageCropDialog");
    const productImageCropCanvas = document.getElementById("productImageCropCanvas");
    const productImageCropZoom = document.getElementById("productImageCropZoom");
    const productImageCropStatus = document.getElementById("productImageCropStatus");
    const cancelProductImageCropButton = document.getElementById("cancelProductImageCrop");
    const confirmProductImageCropButton = document.getElementById("confirmProductImageCrop");
    const productEditorTitle = document.getElementById("product-editor-title");
    const cancelEditButton = document.getElementById("cancelProductEdit");
    const saveProductButton = document.getElementById("saveProductButton");
    const categoryDialog = document.getElementById("categoryDialog");
    const categoryForm = document.getElementById("categoryForm");
    const newCategoryName = document.getElementById("newCategoryName");
    const addCategoryButton = document.getElementById("addCategoryButton");
    const cancelCategoryButton = document.getElementById("cancelCategoryButton");
    const categoryMessage = document.getElementById("categoryMessage");
    const deleteCategoryDialog = document.getElementById("deleteCategoryDialog");
    const deleteCategoryForm = document.getElementById("deleteCategoryForm");
    const deleteCategorySelect = document.getElementById("deleteCategorySelect");
    const deleteCategoryMessage = document.getElementById("deleteCategoryMessage");
    const confirmDeleteCategoryButton = document.getElementById("confirmDeleteCategoryButton");
    const cancelDeleteCategoryButton = document.getElementById("cancelDeleteCategoryButton");
    const productSearch = document.getElementById("productSearch");
    const categoryFilter = document.getElementById("productCategoryFilter");
    const productSort = document.getElementById("productSort");
    const cartPanel = document.getElementById("cartPanel");
    const cartToggle = document.getElementById("cartToggle");
    const cartItems = document.getElementById("cartItems");
    const cartCount = document.getElementById("cartCount");
    const cartTotal = document.getElementById("cartTotal");
    const cartMessage = document.getElementById("cartMessage");
    const checkoutButton = document.getElementById("checkoutButton");
    const ownerTools = document.querySelector(".owner-tools");
    const adminOrderTools = document.getElementById("adminOrderTools");
    const adminOrdersList = document.getElementById("adminOrdersList");
    const adminOrdersStatus = document.getElementById("adminOrdersStatus");
    const refreshOrdersButton = document.getElementById("refreshOrders");
    const moneyFormat = new Intl.NumberFormat("en-MY", {
        style: "currency",
        currency: "MYR"
    });
    let products = [];
    let basket = [];
    let customerAuthenticated = false;
    let editingProductId = null;
    let storageAvailable = false;
    let csrfToken = "";
    let productImageValue = "";
    let imageSelectionVersion = 0;
    let imageProcessing = false;
    let cropImage = null;
    let cropResult = "";
    let resolveCropSelection = null;
    let cropZoom = 1;
    let cropOffsetX = 0;
    let cropOffsetY = 0;
    let cropDragPointerId = null;
    let cropDragStartX = 0;
    let cropDragStartY = 0;
    let cropDragOffsetX = 0;
    let cropDragOffsetY = 0;
    let previousCategory = "";

    function showProductMessage(message) {
        productMessage.textContent = message;
    }

    function categorySymbol(category) {
        if (category === "Fruits") {
            return "🍍";
        }

        if (category === "Herbs") {
            return "🌿";
        }

        return category === "Other produce" ? "🌱" : "🥬";
    }

    function renderShop() {
        const query = productSearch.value.trim().toLowerCase();
        const selectedCategory = categoryFilter.value;
        const sortOrder = productSort.value;
        let visibleProducts = products.filter(function (product) {
            const matchesQuery =
                `${product.name} ${product.description} ${product.category}`.toLowerCase().includes(query);
            const matchesCategory = !selectedCategory || product.category === selectedCategory;
            return matchesQuery && matchesCategory;
        });

        if (sortOrder === "price-low") {
            visibleProducts.sort(function (first, second) {
                return Number(first.price) - Number(second.price);
            });
        } else if (sortOrder === "price-high") {
            visibleProducts.sort(function (first, second) {
                return Number(second.price) - Number(first.price);
            });
        } else if (sortOrder === "name") {
            visibleProducts.sort(function (first, second) {
                return first.name.localeCompare(second.name);
            });
        }

        shopProductList.replaceChildren();
        document.getElementById("productResultCount").textContent =
            `${visibleProducts.length} ${visibleProducts.length === 1 ? "product" : "products"}`;

        if (visibleProducts.length === 0) {
            const emptyState = document.createElement("p");
            emptyState.className = "product-empty-state";
            emptyState.textContent = products.length
                ? "No products match your search. Try another name or category."
                : "There are no products in the catalogue yet.";
            shopProductList.appendChild(emptyState);
            return;
        }

        visibleProducts.forEach(function (product) {
            const card = document.createElement("article");
            card.className = "shop-product-card";

            const imageWrap = document.createElement("div");
            imageWrap.className = "shop-product-image";

            const imageFallback = document.createElement("span");
            imageFallback.className = "shop-image-fallback";
            imageFallback.textContent = categorySymbol(product.category);
            imageFallback.setAttribute("aria-hidden", "true");

            const image = document.createElement("img");
            image.alt = product.name;
            image.loading = "lazy";
            image.addEventListener("error", function () {
                image.hidden = true;
                imageFallback.hidden = false;
            });

            if (typeof product.image === "string" &&
                (/^https?:\/\//i.test(product.image) ||
                    /^data:image\/(?:jpeg|png|webp|gif);base64,/i.test(product.image) ||
                    /^\/(?!\/)/.test(product.image) ||
                    /^(?!.*\.\.)[a-z0-9._~!$&'()*+,;=@%/-]+$/i.test(product.image))) {
                image.src = product.image;
                imageFallback.hidden = true;
            } else {
                image.hidden = true;
            }

            imageWrap.append(imageFallback, image);

            const details = document.createElement("div");
            details.className = "shop-product-details";

            const category = document.createElement("p");
            category.className = "shop-product-category";
            category.textContent = product.category;

            const name = document.createElement("h3");
            name.textContent = product.name;

            const description = document.createElement("p");
            description.className = "shop-product-description";
            description.textContent = product.description;

            const priceRow = document.createElement("div");
            priceRow.className = "shop-product-price-row";

            const price = document.createElement("strong");
            price.className = "shop-product-price";
            price.textContent = moneyFormat.format(Number(product.price));

            const unit = document.createElement("span");
            unit.textContent = ` / ${product.unit || "each"}`;
            priceRow.append(price, unit);

            const addButton = document.createElement("button");
            addButton.type = "button";
            addButton.className = "primary-button add-to-basket-button";
            addButton.dataset.cartAction = "add";
            addButton.dataset.productId = product.id;
            addButton.textContent = "Add to basket";

            details.append(category, name, description, priceRow, addButton);
            card.append(imageWrap, details);
            shopProductList.appendChild(card);
        });
    }

    function renderManagedProducts() {
        productList.replaceChildren();

        if (products.length === 0) {
            const emptyState = document.createElement("p");
            emptyState.className = "product-empty-state";
            emptyState.textContent = "Your catalogue is empty. Add a product to get started.";
            productList.appendChild(emptyState);
            return;
        }

        products.forEach(function (product) {
            const card = document.createElement("article");
            card.className = "product-card";

            const category = document.createElement("p");
            category.className = "product-card-category";
            category.textContent = product.category;

            const name = document.createElement("h3");
            name.textContent = product.name;

            const description = document.createElement("p");
            description.className = "product-card-description";
            description.textContent = product.description;

            const price = document.createElement("strong");
            price.className = "product-card-price";
            price.textContent = `${moneyFormat.format(Number(product.price))} / ${product.unit || "each"}`;

            const actions = document.createElement("div");
            actions.className = "product-card-actions";

            const editButton = document.createElement("button");
            editButton.type = "button";
            editButton.className = "secondary-button";
            editButton.dataset.productAction = "edit";
            editButton.dataset.productId = product.id;
            editButton.textContent = "Edit";

            const deleteButton = document.createElement("button");
            deleteButton.type = "button";
            deleteButton.className = "secondary-button product-delete-button";
            deleteButton.dataset.productAction = "delete";
            deleteButton.dataset.productId = product.id;
            deleteButton.textContent = "Delete";

            actions.append(editButton, deleteButton);
            card.append(category, name, description, price, actions);
            productList.appendChild(card);
        });
    }

    function renderBasket() {
        cartItems.replaceChildren();
        let itemCount = 0;
        let total = 0;

        basket = basket.filter(function (item) {
            return products.some(function (product) {
                return product.id === item.productId;
            });
        });

        basket.forEach(function (item) {
            const product = products.find(function (entry) {
                return entry.id === item.productId;
            });

            if (!product) {
                return;
            }

            itemCount += item.quantity;
            total += Number(product.price) * item.quantity;

            const row = document.createElement("div");
            row.className = "cart-item";

            const description = document.createElement("div");
            const name = document.createElement("strong");
            name.textContent = product.name;
            const quantity = document.createElement("span");
            quantity.textContent = `${item.quantity} × ${moneyFormat.format(Number(product.price))}`;
            description.append(name, quantity);

            const removeButton = document.createElement("button");
            removeButton.type = "button";
            removeButton.className = "cart-remove-button";
            removeButton.dataset.cartAction = "remove";
            removeButton.dataset.productId = product.id;
            removeButton.textContent = "Remove";
            removeButton.setAttribute("aria-label", `Remove ${product.name} from basket`);

            row.append(description, removeButton);
            cartItems.appendChild(row);
        });

        if (itemCount === 0) {
            const emptyState = document.createElement("p");
            emptyState.className = "cart-empty-state";
            emptyState.textContent = "Your basket is empty.";
            cartItems.appendChild(emptyState);
        }

        cartCount.textContent = String(itemCount);
        cartTotal.textContent = moneyFormat.format(total);
        checkoutButton.disabled = itemCount === 0;
    }

    function renderAdminOrders(orders) {
        adminOrdersList.replaceChildren();
        if (orders.length === 0) {
            adminOrdersStatus.textContent = "There are no customer orders yet.";
            return;
        }

        orders.forEach(function (order) {
            const card = document.createElement("article");
            card.className = "admin-record-card";

            const heading = document.createElement("h3");
            heading.textContent = `Order #${order.order_id} · ${moneyFormat.format(Number(order.total_amount))}`;

            const customer = document.createElement("p");
            customer.className = "admin-record-meta";
            customer.textContent = `${order.customer_name} · ${order.customer_email}`;

            const details = document.createElement("p");
            details.className = "admin-record-meta";
            details.textContent = `${order.created_at} · Status: ${order.status}`;

            const itemList = document.createElement("ul");
            itemList.className = "admin-order-items";
            order.items.forEach(function (item) {
                const listItem = document.createElement("li");
                listItem.textContent =
                    `${item.product_name} — ${item.quantity} × ${moneyFormat.format(Number(item.unit_price))} / ${item.selling_unit}`;
                itemList.appendChild(listItem);
            });

            const deleteButton = document.createElement("button");
            deleteButton.type = "button";
            deleteButton.className = "secondary-button danger-button admin-delete-order";
            deleteButton.dataset.orderId = String(order.order_id);
            deleteButton.textContent = "Delete order";
            deleteButton.setAttribute("aria-label", `Delete order ${order.order_id}`);

            card.append(heading, customer, details);
            if (order.items.length > 0) {
                card.appendChild(itemList);
            }
            card.appendChild(deleteButton);
            adminOrdersList.appendChild(card);
        });
        adminOrdersStatus.textContent = `${orders.length} customer order${orders.length === 1 ? "" : "s"}.`;
    }

    async function loadAdminOrders() {
        adminOrdersStatus.textContent = "Loading customer orders...";
        adminOrdersList.replaceChildren();

        try {
            const response = await fetch("admin_orders.php", {
                headers: { Accept: "application/json" }
            });
            const orders = await response.json();
            if (!response.ok || !Array.isArray(orders)) {
                throw new Error(orders.error || "Customer orders could not be loaded.");
            }
            renderAdminOrders(orders);
        } catch (error) {
            adminOrdersStatus.textContent = error.message || "Customer orders could not be loaded.";
            console.error("Unable to load customer orders:", error);
        }
    }

    refreshOrdersButton.addEventListener("click", loadAdminOrders);

    adminOrdersList.addEventListener("click", async function (event) {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }
        const deleteButton = target.closest(".admin-delete-order");
        if (!(deleteButton instanceof HTMLButtonElement)) {
            return;
        }

        const orderId = deleteButton.dataset.orderId;
        if (!orderId || !window.confirm(`Delete order #${orderId} and its items? This cannot be undone.`)) {
            return;
        }

        deleteButton.disabled = true;
        adminOrdersStatus.textContent = `Deleting order #${orderId}...`;
        try {
            const response = await fetch("admin_orders.php", {
                method: "DELETE",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken
                },
                body: JSON.stringify({ order_id: orderId })
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.error || "The order could not be deleted.");
            }
            await loadAdminOrders();
        } catch (error) {
            adminOrdersStatus.textContent = error.message || "The order could not be deleted.";
            deleteButton.disabled = false;
            console.error("Unable to delete customer order:", error);
        }
    });

    async function loadBasket() {
        if (!customerAuthenticated) {
            basket = [];
            renderBasket();
            return;
        }

        const response = await fetch("cart_api.php", {
            headers: { Accept: "application/json" }
        });
        const result = await response.json();
        if (!response.ok || !Array.isArray(result)) {
            throw new Error(result.error || "Your saved basket could not be loaded.");
        }

        basket = result.map(function (item) {
            return {
                productId: String(item.productId),
                quantity: Number(item.quantity)
            };
        });
        renderBasket();
    }

    async function updateBasket(action, productId) {
        const response = await fetch("cart_api.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-Token": csrfToken
            },
            body: JSON.stringify({ action, product_id: productId })
        });
        const result = await response.json();
        if (!response.ok) {
            throw new Error(result.error || "Your basket could not be updated.");
        }
        await loadBasket();
    }

    function renderProducts() {
        renderShop();
        renderManagedProducts();
        renderBasket();
    }

    async function saveProducts(action, product) {
        try {
            const response = await fetch("product_api.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken
                },
                body: JSON.stringify({ action, product })
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.error || "The product could not be saved.");
            }

            await loadProducts();
            return true;
        } catch (error) {
            showProductMessage(error.message || "The product could not be saved. Please try again.");
            console.error("Unable to save product:", error);
            return false;
        }
    }

    function resetProductForm() {
        imageSelectionVersion += 1;
        imageProcessing = false;
        productForm.reset();
        previousCategory = "";
        productImageValue = "";
        productImagePreview.removeAttribute("src");
        productImagePreview.hidden = true;
        productImagePrompt.hidden = false;
        productImageDropzone.classList.remove("is-dragover");
        productImageStatus.textContent = "";
        removeProductImageButton.hidden = true;
        saveProductButton.disabled = !storageAvailable;
        editingProductId = null;
        productEditorTitle.textContent = "Add a product";
        saveProductButton.textContent = "Add product";
        cancelEditButton.hidden = true;
    }

    function displayProductImage(image) {
        productImageValue = image;
        productImagePreview.hidden = !image;
        productImagePrompt.hidden = Boolean(image);
        removeProductImageButton.hidden = !image;

        if (image) {
            productImagePreview.src = image;
            productImageStatus.textContent = "Image ready.";
        } else {
            productImagePreview.removeAttribute("src");
            productImageStatus.textContent = "";
        }
    }

    function loadProductImage(file) {
        if (!file.type.startsWith("image/")) {
            return Promise.reject(new Error("Choose an image file to upload."));
        }

        if (file.size > 12 * 1024 * 1024) {
            return Promise.reject(new Error("Choose an image smaller than 12 MB."));
        }

        return new Promise(function (resolve, reject) {
            const reader = new FileReader();

            reader.onerror = function () {
                reject(new Error("The image could not be read. Please choose another file."));
            };

            reader.onload = function () {
                if (typeof reader.result !== "string") {
                    reject(new Error("The image could not be read. Please choose another file."));
                    return;
                }

                const image = new Image();
                image.onerror = function () {
                    reject(new Error("The image could not be opened. Please choose another file."));
                };
                image.onload = function () {
                    resolve(image);
                };
                image.src = reader.result;
            };

            reader.readAsDataURL(file);
        });
    }

    function getCropImageScale() {
        return productImageCropCanvas.width /
            Math.min(cropImage.naturalWidth, cropImage.naturalHeight) * cropZoom;
    }

    function clampCropOffset() {
        const scale = getCropImageScale();
        const halfWidth = cropImage.naturalWidth * scale / 2;
        const halfHeight = cropImage.naturalHeight * scale / 2;
        const halfCanvas = productImageCropCanvas.width / 2;
        cropOffsetX = Math.max(halfCanvas - halfWidth, Math.min(halfWidth - halfCanvas, cropOffsetX));
        cropOffsetY = Math.max(halfCanvas - halfHeight, Math.min(halfHeight - halfCanvas, cropOffsetY));
    }

    function drawProductImageCrop() {
        if (!cropImage) {
            return;
        }

        clampCropOffset();
        const canvas = productImageCropCanvas;
        const context = canvas.getContext("2d");
        if (!context) {
            throw new Error("Your browser could not display the crop preview.");
        }

        const scale = getCropImageScale();
        const width = cropImage.naturalWidth * scale;
        const height = cropImage.naturalHeight * scale;
        context.clearRect(0, 0, canvas.width, canvas.height);
        context.fillStyle = "#eef4ed";
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(
            cropImage,
            (canvas.width - width) / 2 + cropOffsetX,
            (canvas.height - height) / 2 + cropOffsetY,
            width,
            height
        );
    }

    function openProductImageCrop(image) {
        return new Promise(function (resolve) {
            cropImage = image;
            cropResult = "";
            resolveCropSelection = resolve;
            cropZoom = 1;
            cropOffsetX = 0;
            cropOffsetY = 0;
            productImageCropZoom.value = "1";
            productImageCropStatus.textContent = "";
            drawProductImageCrop();
            productImageCropDialog.showModal();
        });
    }

    function createCroppedProductImage() {
        const outputSize = 1000;
        const scale = getCropImageScale();
        const sourceSize = productImageCropCanvas.width / scale;
        const sourceX = (cropImage.naturalWidth - sourceSize) / 2 - cropOffsetX / scale;
        const sourceY = (cropImage.naturalHeight - sourceSize) / 2 - cropOffsetY / scale;
        const canvas = document.createElement("canvas");
        canvas.width = outputSize;
        canvas.height = outputSize;

        const context = canvas.getContext("2d");
        if (!context) {
            throw new Error("Your browser could not process the cropped image.");
        }

        context.fillStyle = "#ffffff";
        context.fillRect(0, 0, outputSize, outputSize);
        context.drawImage(
            cropImage,
            sourceX,
            sourceY,
            sourceSize,
            sourceSize,
            0,
            0,
            outputSize,
            outputSize
        );

        const compressedImage = canvas.toDataURL("image/jpeg", 0.78);
        if (compressedImage.length > 1500000) {
            throw new Error("This cropped image is still too large. Please choose another image.");
        }
        return compressedImage;
    }

    function finishProductImageCrop(result, returnValue) {
        productImageCropDialog.close(returnValue);
        cropImage = null;
        if (resolveCropSelection) {
            const resolve = resolveCropSelection;
            resolveCropSelection = null;
            resolve(result);
        }
    }

    productImageCropZoom.addEventListener("input", function () {
        cropZoom = Number(productImageCropZoom.value);
        drawProductImageCrop();
    });

    productImageCropCanvas.addEventListener("pointerdown", function (event) {
        if (!cropImage) {
            return;
        }
        cropDragPointerId = event.pointerId;
        cropDragStartX = event.clientX;
        cropDragStartY = event.clientY;
        cropDragOffsetX = cropOffsetX;
        cropDragOffsetY = cropOffsetY;
        productImageCropCanvas.setPointerCapture(event.pointerId);
    });

    productImageCropCanvas.addEventListener("pointermove", function (event) {
        if (event.pointerId !== cropDragPointerId) {
            return;
        }
        const bounds = productImageCropCanvas.getBoundingClientRect();
        const scaleX = productImageCropCanvas.width / bounds.width;
        const scaleY = productImageCropCanvas.height / bounds.height;
        cropOffsetX = cropDragOffsetX + (event.clientX - cropDragStartX) * scaleX;
        cropOffsetY = cropDragOffsetY + (event.clientY - cropDragStartY) * scaleY;
        drawProductImageCrop();
    });

    ["pointerup", "pointercancel", "lostpointercapture"].forEach(function (eventName) {
        productImageCropCanvas.addEventListener(eventName, function (event) {
            if (event.pointerId === cropDragPointerId) {
                cropDragPointerId = null;
            }
        });
    });

    cancelProductImageCropButton.addEventListener("click", function () {
        finishProductImageCrop(null, "cancel");
    });

    confirmProductImageCropButton.addEventListener("click", function () {
        try {
            cropResult = createCroppedProductImage();
            finishProductImageCrop(cropResult, "confirm");
        } catch (error) {
            productImageCropStatus.textContent = error.message;
        }
    });

    productImageCropDialog.addEventListener("cancel", function (event) {
        event.preventDefault();
        finishProductImageCrop(null, "cancel");
    });

    async function selectProductImage(file) {
        if (!file) {
            return;
        }

        const selectionVersion = ++imageSelectionVersion;
        imageProcessing = true;
        saveProductButton.disabled = true;
        productImageStatus.textContent = "Preparing image...";

        try {
            const image = await loadProductImage(file);
            if (selectionVersion === imageSelectionVersion) {
                const croppedImage = await openProductImageCrop(image);
                if (selectionVersion === imageSelectionVersion && croppedImage) {
                    displayProductImage(croppedImage);
                }
            }
        } catch (error) {
            if (selectionVersion === imageSelectionVersion) {
                productImageStatus.textContent = error.message;
            }
        } finally {
            if (selectionVersion === imageSelectionVersion) {
                imageProcessing = false;
                saveProductButton.disabled = !storageAvailable;
                productImageStatus.textContent = productImageValue
                    ? "Image ready."
                    : "";
            }
        }
    }

    async function loadProducts() {
        shopStatus.textContent = "Loading products...";

        try {
            const response = await fetch("get_products.php", {
                headers: { Accept: "application/json" }
            });

            if (!response.ok) {
                throw new Error(`Product request failed (${response.status}).`);
            }

            const databaseProducts = await response.json();
            const validProducts = Array.isArray(databaseProducts) &&
                databaseProducts.every(function (product) {
                    return product &&
                        (typeof product.id === "string" || typeof product.id === "number") &&
                        typeof product.name === "string" &&
                        (typeof product.category === "string" || product.category === null) &&
                        typeof product.description === "string" &&
                        Number.isFinite(Number(product.price));
                });

            if (!validProducts) {
                throw new TypeError("The product API returned an invalid product list.");
            }

            products = databaseProducts.map(function (product) {
                return {
                    id: String(product.id),
                    name: product.name,
                    category: product.category || "Uncategorized",
                    description: product.description,
                    price: Number(product.price),
                    unit: product.unit || "each",
                    image: product.image || ""
                };
            });

            shopStatus.textContent = "";
            renderProducts();
        } catch (error) {
            products = [];
            renderProducts();
            shopStatus.textContent =
                "The product catalogue could not be loaded. Check that Apache and MySQL are running and that the database is configured.";
            console.error("Unable to load products:", error);
        }
    }

    async function loadCategories() {
        const response = await fetch("get_categories.php", {
            headers: { Accept: "application/json" }
        });
        if (!response.ok) {
            throw new Error(`Category request failed (${response.status}).`);
        }

        const categories = await response.json();
        if (!Array.isArray(categories) || !categories.every(function (category) {
            return category &&
                (typeof category.id === "string" || typeof category.id === "number") &&
                typeof category.name === "string";
        })) {
            throw new TypeError("The category API returned an invalid category list.");
        }

        categoryFilter.replaceChildren(new Option("All categories", ""));
        productCategory.replaceChildren(new Option("Choose a category", ""));
        deleteCategorySelect.replaceChildren(new Option("Choose a category to delete", ""));
        categories.forEach(function (category) {
            categoryFilter.add(new Option(category.name, category.name));
            productCategory.add(new Option(category.name, category.name));
            deleteCategorySelect.add(new Option(category.name, String(category.id)));
        });
        productCategory.add(new Option("+ Create a new category...", "__create_category__"));
        productCategory.add(new Option("− Delete a category...", "__delete_category__"));
    }

    productCategory.addEventListener("change", function () {
        if (productCategory.value === "__create_category__") {
            categoryDialog.returnValue = "";
            newCategoryName.value = "";
            categoryMessage.textContent = "";
            productCategory.value = "";
            categoryDialog.showModal();
            newCategoryName.focus();
            return;
        }

        if (productCategory.value === "__delete_category__") {
            deleteCategoryDialog.returnValue = "";
            deleteCategoryMessage.textContent = "";
            deleteCategorySelect.value = "";
            productCategory.value = "";
            if (deleteCategorySelect.options.length <= 1) {
                showProductMessage("There are no categories to delete.");
                return;
            }
            deleteCategoryDialog.showModal();
            deleteCategorySelect.focus();
            return;
        }

        previousCategory = productCategory.value;
    });

    cancelCategoryButton.addEventListener("click", function () {
        categoryDialog.close();
    });

    categoryDialog.addEventListener("close", function () {
        productCategory.value = previousCategory;
    });

    cancelDeleteCategoryButton.addEventListener("click", function () {
        deleteCategoryDialog.close();
    });

    deleteCategoryDialog.addEventListener("close", function () {
        productCategory.value = previousCategory;
    });

    categoryForm.addEventListener("submit", async function (event) {
        event.preventDefault();
        if (!categoryForm.reportValidity() || !storageAvailable) {
            return;
        }

        addCategoryButton.disabled = true;
        categoryMessage.textContent = "";
        try {
            const response = await fetch("product_api.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken
                },
                body: JSON.stringify({
                    action: "create_category",
                    category_name: newCategoryName.value.trim()
                })
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.error || "The category could not be added.");
            }

            await loadCategories();
            productCategory.value = result.category.name;
            categoryFilter.value = "";
            newCategoryName.value = "";
            previousCategory = result.category.name;
            categoryDialog.close();
            showProductMessage(`Category "${result.category.name}" added.`);
        } catch (error) {
            categoryMessage.textContent = error.message || "The category could not be added.";
            console.error("Unable to add category:", error);
        } finally {
            addCategoryButton.disabled = !storageAvailable;
        }
    });

    deleteCategoryForm.addEventListener("submit", async function (event) {
        event.preventDefault();
        if (!deleteCategoryForm.reportValidity() || !storageAvailable) {
            return;
        }

        confirmDeleteCategoryButton.disabled = true;
        deleteCategoryMessage.textContent = "";
        try {
            const response = await fetch("product_api.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken
                },
                body: JSON.stringify({
                    action: "delete_category",
                    category_id: deleteCategorySelect.value
                })
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.error || "The category could not be deleted.");
            }

            await loadCategories();
            productCategory.value = "";
            previousCategory = "";
            categoryFilter.value = "";
            deleteCategoryDialog.close();
            showProductMessage(`Category "${result.category_name}" deleted.`);
            renderProducts();
        } catch (error) {
            deleteCategoryMessage.textContent = error.message || "The category could not be deleted.";
            console.error("Unable to delete category:", error);
        } finally {
            confirmDeleteCategoryButton.disabled = !storageAvailable;
        }
    });

    async function initializeProductPage() {
        try {
            const response = await fetch("auth_status.php", {
                headers: { Accept: "application/json" }
            });
            if (!response.ok) {
                throw new Error(`Account status request failed (${response.status}).`);
            }
            const account = await response.json();
            if (typeof account.authenticated !== "boolean" ||
                typeof account.csrf_token !== "string") {
                throw new Error(account.error || "Account status could not be checked.");
            }
            csrfToken = account.csrf_token;
            customerAuthenticated = account.authenticated;
            storageAvailable = account.authenticated === true &&
                account.role === "admin";
            ownerTools.hidden = !storageAvailable;
            adminOrderTools.hidden = !storageAvailable;
            if (!storageAvailable) {
                ownerTools.querySelectorAll("input, textarea, select, button").forEach(function (field) {
                    field.disabled = true;
                });
            } else {
                loadAdminOrders();
            }
        } catch (error) {
            storageAvailable = false;
            ownerTools.hidden = true;
            adminOrderTools.hidden = true;
            ownerTools.querySelectorAll("input, textarea, select, button").forEach(function (field) {
                field.disabled = true;
            });
            shopStatus.textContent = "Administrator status could not be checked. Refresh the page and try again.";
            console.error("Unable to check account status:", error);
        }

        try {
            await loadCategories();
        } catch (error) {
            shopStatus.textContent = "Product categories could not be loaded. Check the database categories table.";
            console.error("Unable to load product categories:", error);
        }

        await loadProducts();
        try {
            await loadBasket();
            if (!customerAuthenticated) {
                cartMessage.textContent = "Sign in to save your basket and place an order.";
            }
        } catch (error) {
            cartMessage.textContent = error.message;
            console.error("Unable to load saved basket:", error);
        }
    }

    initializeProductPage();

    productForm.addEventListener("submit", async function (event) {
        event.preventDefault();

        if (!productForm.reportValidity() || !storageAvailable) {
            return;
        }

        if (imageProcessing) {
            productImageStatus.textContent = "Wait for the image to finish processing before saving.";
            return;
        }

        const product = {
            id: editingProductId || `${Date.now()}-${Math.random().toString(36).slice(2)}`,
            name: productName.value.trim(),
            category: productCategory.value,
            description: productDescription.value.trim(),
            price: Number(productPrice.value),
            unit: productUnit.value.trim(),
            image: productImageValue
        };

        if (!product.name || !product.description || !product.unit ||
            !Number.isFinite(product.price) || product.price < 0) {
            showProductMessage("Please enter valid product details and price.");
            return;
        }

        const action = editingProductId ? "update" : "create";
        saveProductButton.disabled = true;
        if (await saveProducts(action, product)) {
            showProductMessage(editingProductId ? "Product updated." : "Product added.");
            resetProductForm();
        } else {
            saveProductButton.disabled = !storageAvailable;
        }
    });

    productImage.addEventListener("change", function () {
        selectProductImage(productImage.files[0]);
    });

    productForm.addEventListener("dragover", function (event) {
        event.preventDefault();
    });

    productForm.addEventListener("drop", function (event) {
        event.preventDefault();
    });

    ["dragenter", "dragover"].forEach(function (eventName) {
        productImageDropzone.addEventListener(eventName, function (event) {
            event.preventDefault();
            productImageDropzone.classList.add("is-dragover");
        });
    });

    ["dragleave", "dragend"].forEach(function (eventName) {
        productImageDropzone.addEventListener(eventName, function () {
            productImageDropzone.classList.remove("is-dragover");
        });
    });

    productImageDropzone.addEventListener("drop", function (event) {
        event.preventDefault();
        productImageDropzone.classList.remove("is-dragover");
        selectProductImage(event.dataTransfer.files[0]);
    });

    removeProductImageButton.addEventListener("click", function () {
        imageSelectionVersion += 1;
        imageProcessing = false;
        productImage.value = "";
        displayProductImage("");
        saveProductButton.disabled = !storageAvailable;
    });

    cancelEditButton.addEventListener("click", function () {
        resetProductForm();
        showProductMessage("Editing cancelled.");
    });

    productList.addEventListener("click", async function (event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const actionButton = target.closest("[data-product-action]");

        if (!(actionButton instanceof HTMLButtonElement)) {
            return;
        }

        const productId = actionButton.dataset.productId;
        const selectedProduct = products.find(function (product) {
            return product.id === productId;
        });

        if (!selectedProduct) {
            showProductMessage("That product could not be found. Refresh the page and try again.");
            return;
        }

        if (actionButton.dataset.productAction === "edit") {
            imageSelectionVersion += 1;
            imageProcessing = false;
            saveProductButton.disabled = !storageAvailable;
            editingProductId = selectedProduct.id;
            productName.value = selectedProduct.name;
            productCategory.value = selectedProduct.category;
            previousCategory = selectedProduct.category;
            productDescription.value = selectedProduct.description;
            productPrice.value = String(selectedProduct.price);
            productUnit.value = selectedProduct.unit || "each";
            productImage.value = "";
            displayProductImage(selectedProduct.image || "");
            productEditorTitle.textContent = "Edit product";
            saveProductButton.textContent = "Save changes";
            cancelEditButton.hidden = false;
            ownerTools.open = true;
            productName.focus();
            showProductMessage(`Editing ${selectedProduct.name}.`);
            return;
        }

        if (actionButton.dataset.productAction === "delete") {
            actionButton.disabled = true;
            if (await saveProducts("delete", { id: selectedProduct.id })) {
                if (editingProductId === selectedProduct.id) {
                    resetProductForm();
                }
                showProductMessage(`${selectedProduct.name} deleted.`);
            } else {
                actionButton.disabled = false;
            }
        }
    });

    productSearch.addEventListener("input", renderShop);
    categoryFilter.addEventListener("change", renderShop);
    productSort.addEventListener("change", renderShop);

    shopProductList.addEventListener("click", async function (event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const addButton = target.closest('[data-cart-action="add"]');

        if (!(addButton instanceof HTMLButtonElement)) {
            return;
        }

        const product = products.find(function (entry) {
            return entry.id === addButton.dataset.productId;
        });

        if (!product) {
            shopStatus.textContent = "That product is no longer available. Refresh the page and try again.";
            return;
        }

        if (!customerAuthenticated) {
            window.location.href = "signin.html?return=product";
            return;
        }

        addButton.disabled = true;
        try {
            await updateBasket("add", product.id);
            cartMessage.textContent = `${product.name} added to your saved basket.`;
        } catch (error) {
            cartMessage.textContent = error.message;
        } finally {
            addButton.disabled = false;
        }
    });

    cartItems.addEventListener("click", async function (event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const removeButton = target.closest('[data-cart-action="remove"]');

        if (!(removeButton instanceof HTMLButtonElement)) {
            return;
        }

        const product = products.find(function (entry) {
            return entry.id === removeButton.dataset.productId;
        });
        removeButton.disabled = true;
        try {
            await updateBasket("remove", removeButton.dataset.productId);
            cartMessage.textContent = product ? `${product.name} removed from your basket.` : "Item removed from your basket.";
        } catch (error) {
            cartMessage.textContent = error.message;
            removeButton.disabled = false;
        }
    });

    cartToggle.addEventListener("click", function () {
        cartPanel.hidden = !cartPanel.hidden;
        cartToggle.setAttribute("aria-expanded", String(!cartPanel.hidden));
    });

    document.getElementById("closeCart").addEventListener("click", function () {
        cartPanel.hidden = true;
        cartToggle.setAttribute("aria-expanded", "false");
        cartToggle.focus();
    });

    checkoutButton.addEventListener("click", async function () {
        if (!customerAuthenticated) {
            window.location.href = "signin.html?return=product";
            return;
        }
        if (basket.length === 0) {
            return;
        }

        checkoutButton.disabled = true;
        cartMessage.textContent = "Creating your order...";
        try {
            const response = await fetch("checkout.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken
                },
                body: JSON.stringify({})
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.error || "Your order could not be created.");
            }
            await loadBasket();
            cartMessage.textContent =
                `Order #${result.order_id} created for ${moneyFormat.format(Number(result.total_amount))}. Status: pending. Payment is not yet set up.`;
        } catch (error) {
            cartMessage.textContent = error.message;
        } finally {
            checkoutButton.disabled = basket.length === 0;
        }
    });
}