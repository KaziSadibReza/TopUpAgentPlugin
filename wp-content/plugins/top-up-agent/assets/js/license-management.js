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
  }

  /**
   * Initialize Select2 dropdowns
   */
  function initializeSelect2() {
    // Initialize Select2 for product selectors with enhanced formatting
    $(
      "#selected_products, #edit_selected_products, #bulk_selected_products, .modern-select"
    ).select2({
      placeholder: "Select products...",
      allowClear: true,
      width: "100%",
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

      if (selectedData && selectedData.length === 0) {
        previewText = topUpAgentLicense.strings.allProductsSet;
      } else if (selectedData && selectedData.length === 1) {
        // Simple direct access like the old working code
        var productText =
          selectedData[0].text || selectedData[0].label || "Unknown Product";
        previewText = productText + " Set 1";
      } else if (selectedData && selectedData.length > 1) {
        previewText = topUpAgentLicense.strings.mixedProductsSet;
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

      if (selectedData && selectedData.length === 0) {
        previewText = topUpAgentLicense.strings.bulkAllProductsSet;
      } else if (selectedData && selectedData.length === 1) {
        // Simple direct access like the old working code
        var productText =
          selectedData[0].text || selectedData[0].label || "Unknown Product";
        previewText = productText + " Set 1, Set 2, Set 3...";
      } else if (selectedData && selectedData.length > 1) {
        previewText = topUpAgentLicense.strings.bulkMixedProductsSet;
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
      var isValid = true;
      var errorMessages = [];

      // Validate single license key
      var singleKey = $("#license_key");
      if (singleKey.is(":visible") && singleKey.val().trim() === "") {
        errorMessages.push("License key is required.");
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

  window.confirmDeleteGroup = function () {
    return confirm(topUpAgentLicense.strings.confirmDeleteGroup);
  };

  /**
   * Global copy function for inline onclick handlers
   */
  window.copyToClipboard = copyToClipboard;
})(jQuery);
