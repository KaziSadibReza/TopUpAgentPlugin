/**
 * Top Up Agent - License Management JavaScript
 * Handles all client-side functionality for the License Keys page
 */

(function ($) {
  "use strict";

  // Wait for DOM ready
  $(document).ready(function () {
    initializeLicenseManagement();
  });

  /**
   * Initialize all license management functionality
   */
  function initializeLicenseManagement() {
    initializeSelect2();
    initializeGroupProductHandlers();
    initializeCopyToClipboard();
    initializePreviewUpdaters();
    initializeFormValidation();
    initializeSectionToggles();
  }

  /**
   * Initialize Select2 dropdowns
   */
  function initializeSelect2() {
    // Initialize Select2 for single product selection forms (Add New License Key, Edit License Key)
    $("#selected_products, #edit_selected_products, .single-select").select2({
      placeholder: "Select a product...",
      allowClear: true,
      width: "100%",
      multiple: false,
      templateResult: function (data) {
        if (!data.id) return data.text;

        // Create a span with full text for CSS handling
        var $result = $("<span></span>");
        $result.text(data.text || data.label || "Unknown");
        $result.attr("title", data.text || data.label || "Unknown"); // Full text on hover

        return $result;
      },
      templateSelection: function (data) {
        if (!data.id) return data.text;

        // Return full text for CSS handling
        return data.text || data.label || "Unknown";
      },
      escapeMarkup: function (markup) {
        return markup;
      },
    });

    // Initialize Select2 for Automation Settings (multiple selection)
    $("#automation_enabled_products, .modern-select").select2({
      placeholder: "Select products for automation...",
      allowClear: true,
      width: "100%",
      multiple: true,
      templateResult: function (data) {
        if (!data.id) return data.text;

        var $result = $("<span></span>");
        $result.text(data.text || data.label || "Unknown");
        $result.attr("title", data.text || data.label || "Unknown");

        return $result;
      },
      templateSelection: function (data) {
        if (!data.id) return data.text;

        return data.text || data.label || "Unknown";
      },
      escapeMarkup: function (markup) {
        return markup;
      },
    });

    // Initialize Select2 for filter dropdowns with simpler styling
    $("#product, #status").select2({
      placeholder: function () {
        return $(this).attr("id") === "product"
          ? "All Products"
          : "All Statuses";
      },
      allowClear: true,
      width: "100%",
      minimumResultsForSearch: 5, // Show search only if more than 5 options
      templateResult: function (data) {
        if (!data.id) return data.text;

        // Create a span with full text for CSS handling
        var $result = $("<span></span>");
        $result.text(data.text || data.label || "Unknown");
        $result.attr("title", data.text || data.label || "Unknown"); // Full text on hover

        return $result;
      },
      templateSelection: function (data) {
        if (!data.id) return data.text;

        // Return full text for CSS handling
        return data.text || data.label || "Unknown";
      },
      escapeMarkup: function (markup) {
        return markup;
      },
    });
  }

  /**
   * Initialize group product handlers
   */
  function initializeGroupProductHandlers() {
    // Group product checkbox functionality with multiple key input
    $("#is_group_product").change(function () {
      var $formGrid = $(this).closest(".form-grid");

      if ($(this).is(":checked")) {
        $("#group_settings").show();
        $("#group_license_count_section").show();
        $("#multiple_keys_section").show();
        $("#single_license_key_section").hide();
        // Remove required attribute from single license key when hidden
        $("#license_key").removeAttr("required");
        // Add class to make products section full width
        $formGrid.addClass("single-license-hidden");
      } else {
        $("#group_settings").hide();
        $("#group_license_count_section").hide();
        $("#multiple_keys_section").hide();
        $("#single_license_key_section").show();
        // Add required attribute back to single license key when shown
        $("#license_key").attr("required", "required");
        // Remove class to allow side-by-side layout
        $formGrid.removeClass("single-license-hidden");
      }
    });

    $("#bulk_is_group_product").change(function () {
      var $formGrid = $(this).closest(".form-grid");

      if ($(this).is(":checked")) {
        $("#bulk_group_settings").show();
        // Add class for bulk forms too if needed
        $formGrid.addClass("single-license-hidden");
      } else {
        $("#bulk_group_settings").hide();
        // Remove class for bulk forms
        $formGrid.removeClass("single-license-hidden");
      }
    });

    // Initialize correct layout on page load
    if ($("#is_group_product").is(":checked")) {
      $("#is_group_product")
        .closest(".form-grid")
        .addClass("single-license-hidden");
    }

    if ($("#bulk_is_group_product").is(":checked")) {
      $("#bulk_is_group_product")
        .closest(".form-grid")
        .addClass("single-license-hidden");
    }
  }

  /**
   * Initialize preview updaters for group names
   */
  function initializePreviewUpdaters() {
    // Listen for product selection changes (only if elements exist)
    if ($("#selected_products").length > 0) {
      $("#selected_products").on("change", updateGroupNamePreview);
    }
    if ($("#bulk_selected_products").length > 0) {
      $("#bulk_selected_products").on("change", updateBulkGroupNamePreview);
    }

    // Initial preview update (with delay to ensure Select2 is initialized)
    setTimeout(function () {
      updateGroupNamePreview();
      updateBulkGroupNamePreview();
    }, 100);
  }

  /**
   * Update group name preview when products are selected
   */
  function updateGroupNamePreview() {
    var selectedProducts = $("#selected_products");
    var groupNamePreview = $("#group_name_preview");

    // Check if elements exist and Select2 is initialized
    if (selectedProducts.length === 0 || groupNamePreview.length === 0) {
      return;
    }

    try {
      var selectedData = selectedProducts.select2("data");
      var previewText = topUpAgentLicense.strings.selectProducts;

      // For single selection, selectedData is still an array but with max 1 item
      if (selectedData && selectedData.length === 0) {
        previewText = topUpAgentLicense.strings.allProductsSet;
      } else if (selectedData && selectedData.length === 1) {
        // Simple direct access for single product
        var productText =
          selectedData[0].text || selectedData[0].label || "Unknown Product";
        previewText = productText + " Set 1";
      }

      groupNamePreview.text(previewText);
    } catch (e) {
      // Select2 not initialized yet, ignore
      console.log("Select2 not ready for group name preview");
    }
  }

  /**
   * Update bulk group name preview
   */
  function updateBulkGroupNamePreview() {
    var selectedProducts = $("#bulk_selected_products");
    var groupNamePreview = $("#bulk_group_name_preview");

    // Check if elements exist and Select2 is initialized
    if (selectedProducts.length === 0 || groupNamePreview.length === 0) {
      return;
    }

    try {
      var selectedData = selectedProducts.select2("data");
      var previewText = topUpAgentLicense.strings.selectProducts;

      // For single selection, selectedData is still an array but with max 1 item
      if (selectedData && selectedData.length === 0) {
        previewText = topUpAgentLicense.strings.bulkAllProductsSet;
      } else if (selectedData && selectedData.length === 1) {
        // Simple direct access for single product
        var productText =
          selectedData[0].text || selectedData[0].label || "Unknown Product";
        previewText = productText + " Set 1, Set 2, Set 3...";
      }

      groupNamePreview.text(previewText);
    } catch (e) {
      // Select2 not initialized yet, ignore
      console.log("Select2 not ready for bulk group name preview");
    }
  }

  /**
   * Initialize copy to clipboard functionality
   */
  function initializeCopyToClipboard() {
    // Add click handlers for copy buttons
    $(document).on("click", ".copy-btn", function (e) {
      e.preventDefault();
      var licenseKey = $(this).data("license-key");
      if (licenseKey) {
        copyToClipboard(licenseKey);
      }
    });
  }

  /**
   * Initialize form validation
   */
  function initializeFormValidation() {
    // Add form validation for license key format
    $("form.license-form").on("submit", function (e) {
      var $form = $(this);
      var isValid = true;
      var errorMessages = [];

      // Debug: Log bulk form data before submission
      if ($form.find("#bulk_license_keys").length > 0) {
        var bulkProducts = $("#bulk_selected_products").val();
        console.log("Bulk form submission - Selected products:", bulkProducts);
        console.log("Bulk form data:", $form.serialize());

        // Ensure Select2 values are properly set
        $("#bulk_selected_products").trigger("change.select2");
      }

      // Validate single license key
      var singleKey = $("#license_key");
      if (singleKey.is(":visible") && singleKey.val().trim() === "") {
        errorMessages.push("License key is required.");
        isValid = false;
      }

      // Validate bulk license keys
      var bulkKeys = $("#bulk_license_keys");
      if (bulkKeys.is(":visible") && bulkKeys.val().trim() === "") {
        errorMessages.push("License keys are required for bulk import.");
        isValid = false;
      }

      // Validate multiple license keys for group products
      var multipleKeys = $("#multiple_license_keys");
      var isGroupProduct = $("#is_group_product").is(":checked");

      if (isGroupProduct && multipleKeys.is(":visible")) {
        var keys = multipleKeys
          .val()
          .trim()
          .split("\n")
          .filter(function (key) {
            return key.trim() !== "";
          });

        if (keys.length === 0) {
          errorMessages.push(
            "Multiple license keys are required for group products."
          );
          isValid = false;
        } else if (keys.length < 2) {
          errorMessages.push("Group products require at least 2 license keys.");
          isValid = false;
        }
      }

      // Show errors if any
      if (!isValid) {
        e.preventDefault();
        showNotification(errorMessages.join(" "), "error");
        return false;
      }

      return true;
    });
  }

  /**
   * Copy text to clipboard
   */
  function copyToClipboard(text) {
    // Check if clipboard API is available (HTTPS required)
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard
        .writeText(text)
        .then(function () {
          showNotification(topUpAgentLicense.strings.copySuccess, "success");
        })
        .catch(function (err) {
          console.error("Could not copy text: ", err);
          fallbackCopyToClipboard(text);
        });
    } else {
      // Fallback for non-HTTPS environments
      fallbackCopyToClipboard(text);
    }
  }

  /**
   * Fallback copy method using textarea
   */
  function fallbackCopyToClipboard(text) {
    const textarea = document.createElement("textarea");
    textarea.value = text;
    textarea.style.position = "fixed";
    textarea.style.opacity = "0";
    textarea.style.top = "0";
    textarea.style.left = "0";
    document.body.appendChild(textarea);
    textarea.select();
    textarea.setSelectionRange(0, 99999); // For mobile devices

    try {
      const successful = document.execCommand("copy");
      if (successful) {
        showNotification(topUpAgentLicense.strings.copySuccess, "success");
      } else {
        showNotification(topUpAgentLicense.strings.copyError, "error");
      }
    } catch (err) {
      console.error("Fallback copy failed: ", err);
      showNotification(topUpAgentLicense.strings.copyNotSupported, "error");
    }

    document.body.removeChild(textarea);
  }

  /**
   * Show notification message
   */
  function showNotification(message, type) {
    type = type || "success";

    // Remove any existing notifications
    $(".top-up-agent-notification").remove();

    const notification = $("<div>")
      .addClass("top-up-agent-notification")
      .addClass("top-up-agent-notification-" + type)
      .text(message)
      .css({
        position: "fixed",
        top: "20px",
        right: "20px",
        backgroundColor: type === "error" ? "#ef4444" : "#10b981",
        color: "white",
        padding: "12px 20px",
        borderRadius: "8px",
        zIndex: 9999,
        fontSize: "14px",
        fontWeight: "500",
        boxShadow: "0 4px 12px rgba(0,0,0,0.15)",
        maxWidth: "300px",
        wordWrap: "break-word",
      });

    $("body").append(notification);

    // Auto-remove after 3 seconds
    setTimeout(function () {
      notification.fadeOut(300, function () {
        $(this).remove();
      });
    }, 3000);

    // Add click to dismiss
    notification.on("click", function () {
      $(this).fadeOut(300, function () {
        $(this).remove();
      });
    });
  }

  /**
   * Confirm delete actions
   */
  window.confirmDeleteLicenseKey = function () {
    return confirm(topUpAgentLicense.strings.confirmDelete);
  };

  /**
   * Initialize section toggle functionality
   * Handles show/hide for Add New License Key, Bulk Import, and Automation Settings
   */
  function initializeSectionToggles() {
    // Check localStorage for saved section states
    const savedStates = JSON.parse(
      localStorage.getItem("licensePageSections") || "{}"
    );

    // Initialize sections as hidden by default, unless saved state says otherwise
    const sections = [
      "add-license-section",
      "bulk-import-section",
      "automation-section",
      "filter-section",
      "export-section",
    ];

    sections.forEach((sectionId) => {
      const section = $("#" + sectionId);
      const isVisible = savedStates[sectionId] === true;

      if (section.length) {
        if (isVisible) {
          section.removeClass("hidden").addClass("revealing");
          updateToggleButton(sectionId, true);
        } else {
          section.addClass("hidden");
          updateToggleButton(sectionId, false);
        }
      }
    });

    // Create toggle buttons for filter and export sections if they don't exist
    createMissingToggleButtons();

    // Handle toggle button clicks
    $(".toggle-btn").on("click", function (e) {
      e.preventDefault();

      const targetSection = $(this).data("target");
      const section = $("#" + targetSection);

      if (section.length) {
        const isCurrentlyVisible = !section.hasClass("hidden");

        if (isCurrentlyVisible) {
          // Hide section
          section.removeClass("revealing").addClass("hiding");
          setTimeout(() => {
            section.removeClass("hiding").addClass("hidden");
          }, 300);
          updateToggleButton(targetSection, false);
        } else {
          // Show section
          section.removeClass("hidden hiding").addClass("revealing");
          setTimeout(() => {
            section.removeClass("revealing");
          }, 300);
          updateToggleButton(targetSection, true);
        }

        // Save state to localStorage
        saveSectionState(targetSection, !isCurrentlyVisible);

        // Scroll to section if showing
        if (!isCurrentlyVisible) {
          setTimeout(() => {
            $("html, body").animate(
              {
                scrollTop: section.offset().top - 20,
              },
              500
            );
          }, 150);
        }
      }
    });
  }

  /**
   * Create toggle buttons for filter and export sections if they don't exist
   */
  function createMissingToggleButtons() {
    // Check for search filter panel and create toggle if needed
    const searchFilterPanel = $(".search-filter-panel");
    if (
      searchFilterPanel.length &&
      !$('.toggle-btn[data-target="filter-section"]').length
    ) {
      createToggleButton(
        "filter-section",
        "Search & Filter Options",
        searchFilterPanel
      );
    }

    // Check for export panel and create toggle if needed
    const exportPanel = $(".export-panel");
    if (
      exportPanel.length &&
      !$('.toggle-btn[data-target="export-section"]').length
    ) {
      createToggleButton("export-section", "Export Options", exportPanel);
    }
  }

  /**
   * Create a toggle button for a specific section
   */
  function createToggleButton(sectionId, buttonText, targetElement) {
    // Add ID to target element if it doesn't have one
    if (!targetElement.attr("id")) {
      targetElement.attr("id", sectionId);
    }

    // Find or create section toggles container
    let toggleContainer = $(".section-toggles");
    if (!toggleContainer.length) {
      toggleContainer = $('<div class="section-toggles"></div>');
      targetElement.before(toggleContainer);
    }

    // Create toggle button using existing structure
    const toggleBtn = $(`
      <button type="button" class="toggle-btn" data-target="${sectionId}">
        <span class="text">${buttonText}</span>
        <span class="section-status hidden">Hidden</span>
      </button>
    `);

    toggleContainer.append(toggleBtn);

    // Initialize the section state
    const savedStates = JSON.parse(
      localStorage.getItem("licensePageSections") || "{}"
    );
    const isVisible = savedStates[sectionId] === true;

    if (isVisible) {
      targetElement.removeClass("hidden").addClass("revealing");
      updateToggleButton(sectionId, true);
    } else {
      targetElement.addClass("hidden");
      updateToggleButton(sectionId, false);
    }
  }

  /**
   * Update toggle button appearance and text
   */
  function updateToggleButton(sectionId, isVisible) {
    const button = $(`.toggle-btn[data-target="${sectionId}"]`);
    const statusSpan = button.find(".section-status");

    if (isVisible) {
      button.addClass("active");
      statusSpan.removeClass("hidden").addClass("visible").text("Visible");
    } else {
      button.removeClass("active");
      statusSpan.removeClass("visible").addClass("hidden").text("Hidden");
    }
  }

  /**
   * Save section visibility state to localStorage
   */
  function saveSectionState(sectionId, isVisible) {
    const savedStates = JSON.parse(
      localStorage.getItem("licensePageSections") || "{}"
    );
    savedStates[sectionId] = isVisible;
    localStorage.setItem("licensePageSections", JSON.stringify(savedStates));
  }

  window.confirmDeleteGroup = function () {
    return confirm(topUpAgentLicense.strings.confirmDeleteGroup);
  };

  /**
   * Global copy function for inline onclick handlers
   */
  window.copyToClipboard = copyToClipboard;

  /**
   * Debug function to test bulk form data
   */
  window.debugBulkForm = function () {
    console.log("=== Bulk Form Debug ===");
    console.log(
      "Bulk products element exists:",
      $("#bulk_selected_products").length > 0
    );
    console.log("Bulk products value:", $("#bulk_selected_products").val());
    console.log(
      "Bulk products Select2 data:",
      $("#bulk_selected_products").select2("data")
    );
    console.log(
      "Form serialize:",
      $("form.license-form:has(#bulk_license_keys)").serialize()
    );
    console.log("======================");
  };
})(jQuery);
