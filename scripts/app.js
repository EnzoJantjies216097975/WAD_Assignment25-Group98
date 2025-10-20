// GLOBAL CONFIGURATION

const CONFIG = {
  API_BASE_URL: "/api",
  NOTIFICATION_TIMEOUT: 5000,
  SEARCH_DEBOUNCE: 300,
  AUTO_SAVE_INTERVAL: 30000,
  MAX_RETRIES: 3,
};

// Global state management
const AppState = {
  currentUser: null,
  activeSchedule: null,
  searchCache: new Map(),
  isOnline: navigator.onLine,
};

// INITIALIZATION
$(document).ready(function () {
  console.log("NUST Timetable Manager - Enhanced Version Loaded");

  // Initialize all core components
  initializeGlobalComponents();
  initializePageSpecificComponents();
  initializeEventListeners();
  initializeServiceWorker();

  // Show initial loading animation
  $("body").addClass("fade-in");

  // Load user session and initial data
  loadUserSession();
});

// GLOBAL COMPONENT INITIALIZATION
function initializeGlobalComponents() {
  // Enhanced navigation
  initializeNavigation();

  // Global notifications system
  initializeNotificationSystem();

  // Form validation system
  initializeFormValidationSystem();

  // Auto-save functionality
  initializeAutoSave();

  // Network status monitoring
  initializeNetworkMonitoring();

  // Global error handling
  initializeErrorHandling();

  // Accessibility enhancements
  initializeAccessibility();
}

// ENHANCED NAVIGATION SYSTEM
function initializeNavigation() {
  // Mobile menu with smooth animations
  $(".navbar-toggle")
    .off("click")
    .on("click", function (e) {
      e.preventDefault();
      const $menu = $(".navbar-menu");
      const $icon = $(this).find("i");

      if ($menu.hasClass("active")) {
        $menu.removeClass("active").slideUp(300);
        $icon.removeClass("fa-times").addClass("fa-bars");
      } else {
        $menu.addClass("active").slideDown(300);
        $icon.removeClass("fa-bars").addClass("fa-times");
      }
    });

  // Close mobile menu when clicking outside
  $(document).on("click", function (e) {
    if (!$(e.target).closest(".navbar").length) {
      $(".navbar-menu").removeClass("active").slideUp(300);
      $(".navbar-toggle i").removeClass("fa-times").addClass("fa-bars");
    }
  });

  // Smooth scrolling for anchor links
  $('a[href^="#"]').on("click", function (e) {
    const href = $(this).attr("href");
    if (href !== "#") {
      e.preventDefault();
      const target = $(href);
      if (target.length) {
        $("html, body").animate(
          {
            scrollTop: target.offset().top - 100,
          },
          800,
          "easeInOutCubic"
        );
      }
    }
  });

  // Active page highlighting
  highlightActivePage();

  // Breadcrumb generation
  generateBreadcrumbs();
}

function highlightActivePage() {
  const currentPage = window.location.pathname.split("/").pop();
  $(".nav-link").each(function () {
    const linkPage = $(this).attr("href");
    if (
      linkPage === currentPage ||
      (currentPage === "" && linkPage === "index.html")
    ) {
      $(this).addClass("active");
    }
  });
}

function generateBreadcrumbs() {
  const pathArray = window.location.pathname.split("/").filter(Boolean);
  const breadcrumbsContainer = $("#breadcrumbs");

  if (breadcrumbsContainer.length && pathArray.length > 0) {
    let breadcrumbsHTML =
      '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
    breadcrumbsHTML +=
      '<li class="breadcrumb-item"><a href="index.html">Home</a></li>';

    pathArray.forEach((path, index) => {
      const isLast = index === pathArray.length - 1;
      const title = path.replace(".html", "").replace("-", " ");
      const capitalizedTitle = title.charAt(0).toUpperCase() + title.slice(1);

      if (isLast) {
        breadcrumbsHTML += `<li class="breadcrumb-item active" aria-current="page">${capitalizedTitle}</li>`;
      } else {
        breadcrumbsHTML += `<li class="breadcrumb-item"><a href="${path}">${capitalizedTitle}</a></li>`;
      }
    });

    breadcrumbsHTML += "</ol></nav>";
    breadcrumbsContainer.html(breadcrumbsHTML);
  }
}

// AJAX-DATA LOADING
class DataLoader {
  constructor() {
    this.cache = new Map();
    this.loadingStates = new Set();
  }

  async loadData(endpoint, options = {}) {
    const cacheKey = `${endpoint}_${JSON.stringify(options)}`;

    // Return cached data if available and not expired
    if (this.cache.has(cacheKey) && !options.forceRefresh) {
      const cached = this.cache.get(cacheKey);
      if (Date.now() - cached.timestamp < (options.cacheTime || 300000)) {
        // 5 min default
        return cached.data;
      }
    }

    // Prevent duplicate requests
    if (this.loadingStates.has(cacheKey)) {
      return new Promise((resolve) => {
        const checkInterval = setInterval(() => {
          if (!this.loadingStates.has(cacheKey)) {
            clearInterval(checkInterval);
            resolve(this.cache.get(cacheKey)?.data);
          }
        }, 100);
      });
    }

    this.loadingStates.add(cacheKey);
    showGlobalLoading();

    try {
      const response = await $.ajax({
        url: `${CONFIG.API_BASE_URL}${endpoint}`,
        method: options.method || "GET",
        data: options.data,
        dataType: "json",
        timeout: options.timeout || 10000,
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          "Content-Type": "application/json",
          ...options.headers,
        },
      });

      // Cache successful responses
      this.cache.set(cacheKey, {
        data: response,
        timestamp: Date.now(),
      });

      return response;
    } catch (error) {
      console.error("Data loading error:", error);

      // Try to return cached data as fallback
      if (this.cache.has(cacheKey)) {
        showNotification("Using cached data - network unavailable", "warning");
        return this.cache.get(cacheKey).data;
      }

      throw error;
    } finally {
      this.loadingStates.delete(cacheKey);
      hideGlobalLoading();
    }
  }

  clearCache(pattern = null) {
    if (pattern) {
      for (const key of this.cache.keys()) {
        if (key.includes(pattern)) {
          this.cache.delete(key);
        }
      }
    } else {
      this.cache.clear();
    }
  }
}

// Global instance
const dataLoader = new DataLoader();

// FORM VALIDATION SYSTEM
function initializeFormValidationSystem() {
  // Real-time validation for all forms
  $("form").each(function () {
    const $form = $(this);
    enhanceForm($form);
  });
}

function enhanceForm($form) {
  // Add validation classes and error containers
  $form.find("input, textarea, select").each(function () {
    const $field = $(this);
    const fieldName = $field.attr("name") || $field.attr("id");

    if (fieldName && !$field.next(".error-message").length) {
      $field.after(
        `<div class="error-message" data-field="${fieldName}"></div>`
      );
    }

    // Real-time validation
    $field.on(
      "blur keyup",
      debounce(function () {
        validateField($field);
      }, 300)
    );
  });

  // Enhanced form submission
  $form.on("submit", function (e) {
    e.preventDefault();
    handleFormSubmission($form);
  });
}

function validateField($field) {
  const value = $field.val().trim();
  const rules = $field.data("validate");
  const fieldName = $field.attr("name") || $field.attr("id");

  if (!rules) return true;

  const validationRules = parseValidationRules(rules);
  const result = validateValue(value, validationRules, $field);

  updateFieldValidationState($field, result);

  return result.isValid;
}

function parseValidationRules(rulesString) {
  const rules = {};
  rulesString.split("|").forEach((rule) => {
    if (rule.includes(":")) {
      const [name, value] = rule.split(":");
      rules[name] = value;
    } else {
      rules[rule] = true;
    }
  });
  return rules;
}

function validateValue(value, rules, $field) {
  const result = { isValid: true, message: "" };

  // Required validation
  if (rules.required && !value) {
    result.isValid = false;
    result.message = "This field is required.";
    return result;
  }

  // Skip other validations if field is empty and not required
  if (!value && !rules.required) return result;

  // Email validation
  if (rules.email && !isValidEmail(value)) {
    result.isValid = false;
    result.message = "Please enter a valid email address.";
    return result;
  }

  // NUST email validation
  if (rules.nust && !value.endsWith("@nust.na")) {
    result.isValid = false;
    result.message = "Please use your NUST email address.";
    return result;
  }

  // Minimum length validation
  if (rules.min && value.length < parseInt(rules.min)) {
    result.isValid = false;
    result.message = `Minimum ${rules.min} characters required.`;
    return result;
  }

  // Maximum length validation
  if (rules.max && value.length > parseInt(rules.max)) {
    result.isValid = false;
    result.message = `Maximum ${rules.max} characters allowed.`;
    return result;
  }

  // Digits validation
  if (rules.digits) {
    const digits = parseInt(rules.digits);
    if (!/^\d+$/.test(value) || value.length !== digits) {
      result.isValid = false;
      result.message = `Must be exactly ${digits} digits.`;
      return result;
    }
  }

  // Custom pattern validation
  if (rules.pattern) {
    const pattern = new RegExp(rules.pattern);
    if (!pattern.test(value)) {
      result.isValid = false;
      result.message = "Invalid format.";
      return result;
    }
  }

  // Password strength validation
  if (rules.password && !isStrongPassword(value)) {
    result.isValid = false;
    result.message =
      "Password must contain at least 8 characters with letters and numbers.";
    return result;
  }

  return result;
}

function updateFieldValidationState($field, result) {
  const fieldName = $field.attr("name") || $field.attr("id");
  const $errorDiv = $(`.error-message[data-field="${fieldName}"]`);

  $field.removeClass("error success");

  if (result.isValid && $field.val().trim()) {
    $field.addClass("success");
    $errorDiv.hide().text("");
  } else if (!result.isValid) {
    $field.addClass("error");
    $errorDiv.show().text(result.message);
  } else {
    $errorDiv.hide().text("");
  }
}

async function handleFormSubmission($form) {
  // Validate all fields
  let isValid = true;
  $form.find("[data-validate]").each(function () {
    if (!validateField($(this))) {
      isValid = false;
    }
  });

  if (!isValid) {
    showNotification("Please correct the errors in the form.", "error");
    return;
  }

  const $submitBtn = $form.find('button[type="submit"]');
  const originalText = $submitBtn.html();

  // Show loading state
  $submitBtn
    .prop("disabled", true)
    .html('<i class="fas fa-spinner fa-spin"></i> Submitting...');

  try {
    const formData = new FormData($form[0]);
    const actionUrl = $form.attr("action") || getFormActionUrl($form);

    const response = await $.ajax({
      url: actionUrl,
      method: "POST",
      data: formData,
      processData: false,
      contentType: false,
      dataType: "json",
    });

    handleFormSuccess($form, response);
  } catch (error) {
    handleFormError($form, error);
  } finally {
    $submitBtn.prop("disabled", false).html(originalText);
  }
}

function getFormActionUrl($form) {
  const formId = $form.attr("id");
  const urlMap = {
    loginForm: "/api/auth/login.php",
    registerForm: "/api/auth/register.php",
    contactForm: "/api/contact/submit.php",
    profileForm: "/api/profile/update.php",
  };
  return urlMap[formId] || "/api/form/submit.php";
}

function handleFormSuccess($form, response) {
  showNotification(
    response.message || "Form submitted successfully!",
    "success"
  );

  // Handle specific form types
  const formId = $form.attr("id");
  switch (formId) {
    case "loginForm":
    case "registerForm":
      if (response.redirect) {
        setTimeout(() => {
          window.location.href = response.redirect;
        }, 1500);
      }
      break;
    case "contactForm":
      $form[0].reset();
      $form.find(".success, .error").removeClass("success error");
      break;
  }
}

function handleFormError($form, error) {
  console.error("Form submission error:", error);
  const message =
    error.responseJSON?.message || "An error occurred. Please try again.";
  showNotification(message, "error");
}

// LIVE SEARCH IMPLEMENTATION
function initializeLiveSearch() {
  $(".live-search").each(function () {
    const $input = $(this);
    const resultsContainer = $input.data("results") || "#searchResults";
    const suggestionsContainer =
      $input.data("suggestions") || "#searchSuggestions";

    let searchTimeout;
    let currentRequest = null;

    $input.on("input", function () {
      const query = $(this).val().trim();

      clearTimeout(searchTimeout);

      if (currentRequest) {
        currentRequest.abort();
      }

      if (query.length < 2) {
        $(resultsContainer).empty();
        $(suggestionsContainer).hide();
        return;
      }

      searchTimeout = setTimeout(async () => {
        try {
          showSearchLoading(resultsContainer);

          currentRequest = $.ajax({
            url: "/api/courses/search.php",
            data: { q: query },
            dataType: "json",
          });

          const response = await currentRequest;
          displaySearchResults(response.data, resultsContainer);
          displaySearchSuggestions(response.suggestions, suggestionsContainer);
        } catch (error) {
          if (error.statusText !== "abort") {
            console.error("Search error:", error);
            displaySearchError(resultsContainer);
          }
        } finally {
          currentRequest = null;
        }
      }, CONFIG.SEARCH_DEBOUNCE);
    });

    // Handle keyboard navigation for suggestions
    $input.on("keydown", function (e) {
      handleSuggestionNavigation(e, suggestionsContainer);
    });
  });
}

function displaySearchResults(results, container) {
  const $container = $(container);

  if (!results || results.length === 0) {
    $container.html(`
            <div class="text-center text-muted p-4">
                <i class="fas fa-search-minus fa-3x opacity-50"></i>
                <p class="mt-2">No results found</p>
            </div>
        `);
    return;
  }

  let html = '<div class="search-results">';
  results.forEach((item, index) => {
    html += createSearchResultItem(item, index);
  });
  html += "</div>";

  $container.html(html).addClass("fade-in");

  // Animate results
  $(".search-result-item").each(function (index) {
    $(this)
      .delay(index * 50)
      .animate({ opacity: 1 }, 300);
  });
}

function createSearchResultItem(item, index) {
  return `
        <div class="search-result-item mb-3" style="opacity: 0;">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-primary mb-2">${item.code}</h6>
                    <p class="mb-2">${item.name}</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="fas fa-star"></i> ${
                              item.credits
                            } credits •
                            <i class="fas fa-user"></i> ${
                              item.lecturers[0] || "TBA"
                            }
                        </small>
                        <button class="btn btn-sm btn-primary add-to-schedule" 
                                data-course-code="${item.code}">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function showSearchLoading(container) {
  $(container).html(`
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Searching...</span>
            </div>
            <p class="mt-2 text-muted">Searching...</p>
        </div>
    `);
}

function displaySearchError(container) {
  $(container).html(`
        <div class="text-center text-muted p-4">
            <i class="fas fa-exclamation-triangle fa-3x text-warning"></i>
            <p class="mt-2">Search error occurred</p>
            <button class="btn btn-sm btn-outline-primary retry-search">
                <i class="fas fa-redo"></i> Try Again
            </button>
        </div>
    `);
}

// NOTIFICATION SYSTEM
function initializeNotificationSystem() {
  // Create notification container if it doesn't exist
  if (!$("#notification-container").length) {
    $("body").append(
      '<div id="notification-container" style="position: fixed; top: 20px; right: 20px; z-index: 10000;"></div>'
    );
  }
}

function showNotification(
  message,
  type = "info",
  duration = CONFIG.NOTIFICATION_TIMEOUT
) {
  const id = "notification-" + Date.now();
  const icon = getNotificationIcon(type);

  const notification = $(`
        <div id="${id}" class="notification notification-${type}" style="
            transform: translateX(400px);
            transition: transform 0.3s ease-out;
            margin-bottom: 10px;
            padding: 1rem 1.5rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-left: 4px solid ${getNotificationColor(type)};
            max-width: 400px;
        ">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <i class="${icon}" style="color: ${getNotificationColor(
    type
  )};"></i>
                <span>${message}</span>
                <button class="notification-close" style="
                    background: none;
                    border: none;
                    margin-left: auto;
                    cursor: pointer;
                    opacity: 0.5;
                " onclick="closeNotification('${id}')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    `);

  $("#notification-container").append(notification);

  // Trigger slide-in animation
  setTimeout(() => {
    notification.css("transform", "translateX(0)");
  }, 100);

  // Auto-hide after duration
  if (duration > 0) {
    setTimeout(() => {
      closeNotification(id);
    }, duration);
  }

  return id;
}

function closeNotification(id) {
  const $notification = $(`#${id}`);
  $notification.css("transform", "translateX(400px)");
  setTimeout(() => {
    $notification.remove();
  }, 300);
}

function getNotificationIcon(type) {
  const icons = {
    success: "fas fa-check-circle",
    error: "fas fa-exclamation-circle",
    warning: "fas fa-exclamation-triangle",
    info: "fas fa-info-circle",
  };
  return icons[type] || icons.info;
}

function getNotificationColor(type) {
  const colors = {
    success: "#10b981",
    error: "#dc2626",
    warning: "#f59e0b",
    info: "#3b82f6",
  };
  return colors[type] || colors.info;
}

// AUTO-SAVE FUNCTIONALITY
function initializeAutoSave() {
  // Auto-save forms periodically
  setInterval(() => {
    saveFormData();
  }, CONFIG.AUTO_SAVE_INTERVAL);

  // Save on form changes
  $(document).on(
    "change",
    "form input, form textarea, form select",
    debounce(saveFormData, 1000)
  );
}

function saveFormData() {
  $("form[data-autosave]").each(function () {
    const $form = $(this);
    const formId = $form.attr("id") || "unnamed-form";
    const formData = {};

    $form.find("input, textarea, select").each(function () {
      const $field = $(this);
      const name = $field.attr("name");
      if (name && $field.attr("type") !== "password") {
        formData[name] = $field.val();
      }
    });

    localStorage.setItem(
      `autosave_${formId}`,
      JSON.stringify({
        data: formData,
        timestamp: Date.now(),
      })
    );
  });
}

function loadSavedFormData() {
  $("form[data-autosave]").each(function () {
    const $form = $(this);
    const formId = $form.attr("id") || "unnamed-form";
    const saved = localStorage.getItem(`autosave_${formId}`);

    if (saved) {
      try {
        const { data, timestamp } = JSON.parse(saved);

        // Only load if saved within last 24 hours
        if (Date.now() - timestamp < 86400000) {
          Object.keys(data).forEach((name) => {
            const $field = $form.find(`[name="${name}"]`);
            if ($field.length && $field.val() === "") {
              $field.val(data[name]);
            }
          });

          showNotification("Form data restored from auto-save", "info");
        }
      } catch (error) {
        console.error("Error loading saved form data:", error);
      }
    }
  });
}

// NETWORK STATUS MONITORING
function initializeNetworkMonitoring() {
  window.addEventListener("online", () => {
    AppState.isOnline = true;
    showNotification("Connection restored", "success");
    syncOfflineData();
  });

  window.addEventListener("offline", () => {
    AppState.isOnline = false;
    showNotification("Working offline", "warning");
  });
}

function syncOfflineData() {
  // Sync any offline changes when connection is restored
  const offlineData = JSON.parse(localStorage.getItem("offline_data") || "[]");

  if (offlineData.length > 0) {
    showNotification("Syncing offline changes...", "info");

    offlineData.forEach(async (item) => {
      try {
        await $.ajax({
          url: item.url,
          method: item.method,
          data: item.data,
        });
      } catch (error) {
        console.error("Sync error:", error);
      }
    });

    localStorage.removeItem("offline_data");
    showNotification("Offline changes synced", "success");
  }
}

// ACCESSIBILITY ENHANCEMENTS
function initializeAccessibility() {
  // Enhanced keyboard navigation
  $(document).on("keydown", function (e) {
    // Skip to main content (Alt+M)
    if (e.altKey && e.key === "m") {
      e.preventDefault();
      $("#main-content").focus();
    }

    // Toggle navigation (Alt+N)
    if (e.altKey && e.key === "n") {
      e.preventDefault();
      $(".navbar-toggle").click();
    }
  });

  // Add skip links
  if (!$(".skip-links").length) {
    $("body").prepend(`
            <div class="skip-links" style="position: absolute; top: -40px; left: 6px; z-index: 1000;">
                <a href="#main-content" class="btn btn-primary" style="position: absolute; top: -40px;">
                    Skip to main content
                </a>
            </div>
        `);

    $(".skip-links a")
      .on("focus", function () {
        $(this).css("top", "6px");
      })
      .on("blur", function () {
        $(this).css("top", "-40px");
      });
  }

  // Improve form accessibility
  $("form").each(function () {
    const $form = $(this);

    // Add required indicators
    $form.find("[required]").each(function () {
      const $field = $(this);
      const $label = $form.find(`label[for="${$field.attr("id")}"]`);
      if ($label.length && !$label.find(".required-indicator").length) {
        $label.append(
          ' <span class="required-indicator" aria-label="required">*</span>'
        );
      }
    });

    // Improve error message accessibility
    $form.find(".error-message").attr("role", "alert");
  });
}

// PAGE-SPECIFIC INITIALIZATION
function initializePageSpecificComponents() {
  const currentPage = window.location.pathname.split("/").pop();

  switch (currentPage) {
    case "login.html":
      initializeLoginPage();
      break;
    case "dashboard.html":
      initializeDashboardPage();
      break;
    case "course-browser.html":
      initializeCourseBrowserPage();
      break;
    case "schedule-editor.html":
      initializeScheduleEditorPage();
      break;
    case "my-schedules.html":
      initializeMySchedulesPage();
      break;
    default:
      // Initialize common components for all pages
      initializeLiveSearch();
      loadSavedFormData();
  }
}

function initializeLoginPage() {
  // Enhanced login form
  $("#loginForm, #registerForm").each(function () {
    enhanceForm($(this));
  });

  // Tab switching animation
  $(".tab-button").on("click", function () {
    const target = $(this).data("target");
    $(".tab-content").removeClass("active").hide();
    $(target).addClass("active").fadeIn(300);
    $(".tab-button").removeClass("active");
    $(this).addClass("active");
  });
}

function initializeDashboardPage() {
  loadDashboardData();
  initializeQuickActions();
  initializeWidgets();
}

function initializeCourseBrowserPage() {
  initializeLiveSearch();
  initializeCourseFilters();
  loadAvailableCourses();
}

function initializeScheduleEditorPage() {
  initializeDragAndDrop();
  initializeScheduleGrid();
  loadScheduleData();
}

function initializeMySchedulesPage() {
  loadUserSchedules();
  initializeScheduleActions();
}

// UTILITY FUNCTIONS
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function isStrongPassword(password) {
  return (
    password.length >= 8 && /[a-zA-Z]/.test(password) && /[0-9]/.test(password)
  );
}

function showGlobalLoading() {
  if (!$("#global-loading").length) {
    $("body").append(`
            <div id="global-loading" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            ">
                <div class="spinner-border text-light" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
            </div>
        `);
  } else {
    $("#global-loading").show();
  }
}

function hideGlobalLoading() {
  $("#global-loading").hide();
}

function formatDate(date) {
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function formatTime(time) {
  return new Date(`2000-01-01 ${time}`).toLocaleTimeString("en-US", {
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
  });
}

// USER SESSION MANAGEMENT
async function loadUserSession() {
  try {
    const response = await dataLoader.loadData("/auth/session.php");
    if (response.success && response.user) {
      AppState.currentUser = response.user;
      updateUIForLoggedInUser();
    } else {
      // Redirect to login if on protected page
      const protectedPages = [
        "dashboard.html",
        "my-schedules.html",
        "schedule-editor.html",
      ];
      const currentPage = window.location.pathname.split("/").pop();
      if (protectedPages.includes(currentPage)) {
        window.location.href = "login.html";
      }
    }
  } catch (error) {
    console.error("Session loading error:", error);
  }
}

function updateUIForLoggedInUser() {
  // Update user info in navigation
  $(".user-name").text(AppState.currentUser.full_name);
  $(".user-email").text(AppState.currentUser.email);

  // Update user avatar initials
  const initials = AppState.currentUser.full_name
    .split(" ")
    .map((name) => name[0])
    .join("")
    .toUpperCase();
  $(".user-initials").text(initials);
}

// ERROR HANDLING
function initializeErrorHandling() {
  // Global error handler
  window.addEventListener("error", function (e) {
    console.error("Global error:", e.error);
    showNotification("An unexpected error occurred", "error");
  });

  // Unhandled promise rejection handler
  window.addEventListener("unhandledrejection", function (e) {
    console.error("Unhandled promise rejection:", e.reason);
    showNotification(
      "An error occurred while processing your request",
      "error"
    );
  });

  // AJAX error handler
  $(document).ajaxError(function (event, xhr, settings, error) {
    console.error("AJAX Error:", {
      url: settings.url,
      status: xhr.status,
      error: error,
    });

    if (xhr.status === 401) {
      showNotification("Session expired. Please log in again.", "error");
      setTimeout(() => {
        window.location.href = "login.html";
      }, 2000);
    } else if (xhr.status === 403) {
      showNotification("Access denied.", "error");
    } else if (xhr.status === 404) {
      showNotification("Requested resource not found.", "error");
    } else if (xhr.status >= 500) {
      showNotification("Server error. Please try again later.", "error");
    }
  });
}

// EVENT LISTENERS
function initializeEventListeners() {
  // Global click handlers
  $(document).on("click", "[data-action]", function (e) {
    e.preventDefault();
    const action = $(this).data("action");
    const target = $(this).data("target");
    const value = $(this).data("value");

    handleGlobalAction(action, target, value, this);
  });

  // Form submission handlers
  $(document).on("submit", "form", function (e) {
    if (!$(this).hasClass("enhanced")) {
      e.preventDefault();
      enhanceForm($(this));
      $(this).addClass("enhanced").submit();
    }
  });

  // Dynamic content loading
  $(document).on("click", "[data-load]", function (e) {
    e.preventDefault();
    const url = $(this).data("load");
    const target = $(this).data("target") || "#main-content";
    loadContentIntoContainer(url, target);
  });
}

function handleGlobalAction(action, target, value, element) {
  switch (action) {
    case "toggle":
      $(target).toggle();
      break;
    case "show":
      $(target).show();
      break;
    case "hide":
      $(target).hide();
      break;
    case "load-data":
      loadDataAction(target, value);
      break;
    case "refresh":
      refreshComponent(target);
      break;
    case "delete":
      confirmDelete(target, value);
      break;
    default:
      console.log("Unknown action:", action);
  }
}

async function loadDataAction(endpoint, target) {
  try {
    const data = await dataLoader.loadData(endpoint);
    $(target).html(data.html || JSON.stringify(data));
  } catch (error) {
    $(target).html('<div class="alert alert-danger">Failed to load data</div>');
  }
}

function refreshComponent(selector) {
  const $element = $(selector);
  $element.addClass("refreshing");

  // Simulate refresh animation
  setTimeout(() => {
    $element.removeClass("refreshing");
    showNotification("Component refreshed", "success");
  }, 1000);
}

function confirmDelete(endpoint, itemId) {
  if (confirm("Are you sure you want to delete this item?")) {
    deleteItem(endpoint, itemId);
  }
}

async function deleteItem(endpoint, itemId) {
  try {
    await $.ajax({
      url: `${CONFIG.API_BASE_URL}${endpoint}`,
      method: "DELETE",
      data: { id: itemId },
    });

    showNotification("Item deleted successfully", "success");

    // Remove item from UI
    $(`[data-item-id="${itemId}"]`).fadeOut(300, function () {
      $(this).remove();
    });
  } catch (error) {
    showNotification("Failed to delete item", "error");
  }
}

// SERVICE WORKER INITIALIZATION
function initializeServiceWorker() {
  if ("serviceWorker" in navigator) {
    navigator.serviceWorker
      .register("/sw.js")
      .then((registration) => {
        console.log("Service Worker registered:", registration);
      })
      .catch((error) => {
        console.error("Service Worker registration failed:", error);
      });
  }
}

// EXPORT FOR USE IN OTHER FILES
window.NUSTApp = {
  showNotification,
  showGlobalLoading,
  hideGlobalLoading,
  dataLoader,
  validateField,
  debounce,
  formatDate,
  formatTime,
  AppState,
};

// Dashboard initialization - now properly checks authentication
document.addEventListener("DOMContentLoaded", function () {
  // Check if user is logged in before loading anything
  checkUserAuthentication()
    .then(() => {
      // User is authenticated, load dashboard data
      loadUserData();
      loadDashboardStats();
      loadRecentSchedules();

      // Show welcome message
      if (typeof NUSTApp !== "undefined") {
        NUSTApp.showNotification("Welcome to your dashboard!", "success");
      }
    })
    .catch((error) => {
      console.error("Authentication failed:", error);
      // Redirect to login if not authenticated
      redirectToLogin();
    });
});

/**
 * Check if user is properly authenticated
 * This function calls your PHP session check API
 */
async function checkUserAuthentication() {
  try {
    const response = await fetch("/api/auth/session-check.php", {
      method: "GET",
      credentials: "include", // Include cookies for session
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    });

    const data = await response.json();

    if (!response.ok || !data.success || !data.user) {
      throw new Error("Not authenticated");
    }

    // Store user data globally for use across the app
    window.currentUser = data.user;
    return data.user;
  } catch (error) {
    console.error("Authentication check failed:", error);
    throw error;
  }
}

/**
 * Load real user data from the backend
 * This replaces the hardcoded Nathan Duarte data
 */
async function loadUserData() {
  try {
    // Show loading state
    showLoadingState("userProfile");

    const response = await fetch("/api/profile/get.php", {
      method: "GET",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    if (!data.success) {
      throw new Error(data.error || "Failed to load user data");
    }

    const user = data.profile;

    // Update UI with real user data
    updateUserInterface(user);

    // Hide loading state
    hideLoadingState("userProfile");
  } catch (error) {
    console.error("Failed to load user data:", error);
    hideLoadingState("userProfile");

    if (typeof NUSTApp !== "undefined") {
      NUSTApp.showNotification("Failed to load user profile", "error");
    }

    // Fallback to login if user data can't be loaded
    setTimeout(() => redirectToLogin(), 2000);
  }
}

/**
 * Update the user interface with real user data
 */
function updateUserInterface(user) {
  // Generate initials from full name
  const initials = generateInitials(user.full_name);

  // Update user display elements
  const userNameElement = document.getElementById("userName");
  const welcomeNameElement = document.getElementById("welcomeName");
  const userInitialsElement = document.getElementById("userInitials");
  const userProgramElement = document.getElementById("userProgram");

  if (userNameElement) userNameElement.textContent = user.full_name;
  if (welcomeNameElement)
    welcomeNameElement.textContent = user.full_name.split(" ")[0];
  if (userInitialsElement) userInitialsElement.textContent = initials;
  if (userProgramElement) userProgramElement.textContent = user.program;

  // Update other profile-related elements
  const profileInitialsElements = document.querySelectorAll(
    ".user-initials, .profile-initials"
  );
  profileInitialsElements.forEach((element) => {
    element.textContent = initials;
  });

  console.log("User interface updated with:", user.full_name);
}

/**
 * Load real dashboard statistics from the backend
 * This replaces the hardcoded statistics
 */
async function loadDashboardStats() {
  try {
    // Show loading state for stats
    showLoadingState("dashboardStats");

    const response = await fetch("/api/dashboard/stats.php", {
      method: "GET",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    if (!data.success) {
      throw new Error(data.error || "Failed to load dashboard stats");
    }

    // Update statistics in the UI
    updateDashboardStatistics(data.dashboard_data);

    hideLoadingState("dashboardStats");
  } catch (error) {
    console.error("Failed to load dashboard stats:", error);
    hideLoadingState("dashboardStats");

    // Show fallback/default stats
    showFallbackStats();

    if (typeof NUSTApp !== "undefined") {
      NUSTApp.showNotification("Could not load latest statistics", "warning");
    }
  }
}

/**
 * Update dashboard statistics in the UI
 */
function updateDashboardStatistics(dashboardData) {
  const stats = dashboardData.user_statistics;

  // Update stat cards with real data
  const totalSchedulesElement = document.getElementById("totalSchedules");
  const activeSchedulesElement = document.getElementById("activeSchedules");
  const totalCoursesElement = document.getElementById("totalCourses");
  const sharedSchedulesElement = document.getElementById("sharedSchedules");

  if (totalSchedulesElement) {
    totalSchedulesElement.textContent = stats.schedules.total || 0;
  }
  if (activeSchedulesElement) {
    activeSchedulesElement.textContent = stats.schedules.active || 0;
  }
  if (totalCoursesElement) {
    totalCoursesElement.textContent = stats.courses.unique_courses || 0;
  }

  // For shared schedules, we'll need to implement this in the backend
  // For now, use a default value
  if (sharedSchedulesElement) {
    sharedSchedulesElement.textContent = 0; // Update when sharing is implemented
  }

  console.log("Dashboard statistics updated");
}

/**
 * Load real recent schedules from the backend
 */
async function loadRecentSchedules() {
  try {
    const response = await fetch(
      "/api/schedules/list.php?sort=updated_at&order=DESC&limit=3",
      {
        method: "GET",
        credentials: "include",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      }
    );

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    if (data.success && data.data) {
      updateRecentSchedulesList(data.data);
    }
  } catch (error) {
    console.error("Failed to load recent schedules:", error);
    // Keep the existing hardcoded schedules as fallback
  }
}

/**
 * Update the recent schedules list with real data
 */
function updateRecentSchedulesList(schedules) {
  const recentSchedulesContainer = document.getElementById(
    "recentSchedulesList"
  );

  if (!recentSchedulesContainer || schedules.length === 0) {
    return;
  }

  // Clear existing content
  recentSchedulesContainer.innerHTML = "";

  schedules.forEach((schedule) => {
    const scheduleItem = document.createElement("div");
    scheduleItem.className = "schedule-item";
    scheduleItem.onclick = () => openSchedule(schedule.id);

    // Determine badge color based on status
    let badgeClass = "badge-secondary";
    if (schedule.status === "active") badgeClass = "badge-primary";
    else if (schedule.status === "draft") badgeClass = "badge-secondary";
    else if (schedule.status === "archived") badgeClass = "badge";

    scheduleItem.innerHTML = `
            <div class="schedule-header">
                <div class="schedule-name">
                    <i class="fas fa-calendar"></i> ${schedule.name}
                </div>
                <span class="badge ${badgeClass}">${
      schedule.status.charAt(0).toUpperCase() + schedule.status.slice(1)
    }</span>
            </div>
            <div class="schedule-meta">
                <i class="fas fa-book"></i> ${
                  schedule.course_count || 0
                } courses &nbsp;|&nbsp;
                <i class="fas fa-clock"></i> ${
                  schedule.updated_ago || "Recently updated"
                }
            </div>
        `;

    recentSchedulesContainer.appendChild(scheduleItem);
  });
}

/**
 * Utility Functions
 */

// Generate initials from full name
function generateInitials(fullName) {
  if (!fullName) return "U"; // Default for "User"

  return fullName
    .split(" ")
    .map((name) => name.charAt(0).toUpperCase())
    .join("")
    .substring(0, 2); // Max 2 characters
}

// Show loading state for different sections
function showLoadingState(section) {
  const loadingHTML = `
        <div class="loading-placeholder">
            <i class="fas fa-spinner fa-spin"></i> Loading...
        </div>
    `;

  switch (section) {
    case "userProfile":
      // Show loading in user profile area
      break;
    case "dashboardStats":
      // Could show loading spinners in stat cards
      break;
  }
}

// Hide loading state
function hideLoadingState(section) {
  const loadingElements = document.querySelectorAll(".loading-placeholder");
  loadingElements.forEach((element) => element.remove());
}

// Show fallback statistics when API fails
function showFallbackStats() {
  const fallbackStats = {
    totalSchedules: 0,
    activeSchedules: 0,
    totalCourses: 0,
    sharedSchedules: 0,
  };

  Object.keys(fallbackStats).forEach((statKey) => {
    const element = document.getElementById(statKey);
    if (element) {
      element.textContent = fallbackStats[statKey];
    }
  });
}

// Redirect to login page
function redirectToLogin() {
  // Clear any stored session data
  localStorage.removeItem("userSession");
  sessionStorage.clear();

  // Redirect with a message
  window.location.href = "login.html?message=session_expired";
}

/**
 * Navigation and UI Functions
 */

// Open a specific schedule in the editor
function openSchedule(scheduleId) {
  window.location.href = `schedule-editor.html?id=${scheduleId}`;
}

// Toggle mobile menu
function toggleMobileMenu() {
  const navbarMenu = document.getElementById("navbarMenu");
  if (navbarMenu) {
    navbarMenu.classList.toggle("active");
  }
}

// Show notifications (placeholder for future feature)
function showNotifications() {
  if (typeof NUSTApp !== "undefined") {
    NUSTApp.showNotification("Notifications feature coming soon!", "info");
  } else {
    alert("Notifications feature coming soon!");
  }
}

// Logout function with proper session cleanup
async function logout() {
  if (confirm("Are you sure you want to logout?")) {
    try {
      // Call logout API
      await fetch("/api/auth/logout.php", {
        method: "POST",
        credentials: "include",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      });
    } catch (error) {
      console.error("Logout API call failed:", error);
    } finally {
      // Always clean up and redirect, even if API call fails
      localStorage.removeItem("userSession");
      sessionStorage.clear();
      window.location.href = "login.html";
    }
  }
}
