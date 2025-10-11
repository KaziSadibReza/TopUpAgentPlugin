/**
 * Top Up Agent - Socket.IO Integration JavaScript
 * Handles real-time communication with the automation server
 */

(function ($) {
  "use strict";

  // Global variables
  let socket = null;
  let connectionAttempts = 0;
  const maxConnectionAttempts = 5;
  let reconnectTimeout = null;
  let isConnecting = false;

  // Configuration from WordPress
  const config = window.topUpAgentWebSocket || {};
  const serverUrl = config.serverUrl || "https://server.uidtopupbd.com";
  const debugMode = config.debug || false;
  const isAdmin = config.isAdmin || false;

  /**
   * Debug logging function
   */
  function debug(...args) {
    if (debugMode) {
      console.log("[TopUpAgent Socket.IO]", ...args);
    }
  }

  /**
   * Initialize Socket.IO connection
   */
  function initializeSocket() {
    if (typeof io === "undefined") {
      console.error("Socket.IO library not loaded");
      return;
    }

    if (socket && socket.connected) {
      debug("Socket already connected");
      return;
    }

    if (isConnecting) {
      debug("Connection attempt already in progress");
      return;
    }

    isConnecting = true;
    debug("Initializing Socket.IO connection to:", serverUrl);

    try {
      socket = io(serverUrl, {
        transports: ["websocket", "polling"],
        timeout: 10000,
        forceNew: true,
        reconnection: true,
        reconnectionAttempts: maxConnectionAttempts,
        reconnectionDelay: 2000,
        reconnectionDelayMax: 10000,
      });

      // Connection events
      socket.on("connect", handleConnect);
      socket.on("disconnect", handleDisconnect);
      socket.on("connect_error", handleConnectError);
      socket.on("reconnect", handleReconnect);
      socket.on("reconnect_error", handleReconnectError);
      socket.on("reconnect_failed", handleReconnectFailed);

      // Custom events
      socket.on("queue_update", handleQueueUpdate);
      socket.on("automation_progress", handleAutomationProgress);
      socket.on("automation_complete", handleAutomationComplete);
      socket.on("automation_error", handleAutomationError);
      socket.on("job-update", handleJobUpdate); // Handle job updates from server
      socket.on("server_status", handleServerStatus);
      socket.on("log_message", handleLogMessage);
    } catch (error) {
      console.error("Failed to initialize Socket.IO:", error);
      isConnecting = false;
      updateConnectionStatus("error");
    }
  }

  /**
   * Handle successful connection
   */
  function handleConnect() {
    debug("Connected to server");
    isConnecting = false;
    connectionAttempts = 0;
    updateConnectionStatus("connected");

    // Clear any reconnection timeout
    if (reconnectTimeout) {
      clearTimeout(reconnectTimeout);
      reconnectTimeout = null;
    }

    // Join the automation room to receive job updates
    socket.emit("join-room", "automation");
    debug("Joined automation room for job updates");

    // Request initial status
    requestServerStatus();

    // Trigger custom event for other scripts
    $(document).trigger("topUpAgent:connected");
  }

  /**
   * Handle disconnection
   */
  function handleDisconnect(reason) {
    debug("Disconnected from server:", reason);
    isConnecting = false;
    updateConnectionStatus("disconnected");

    // Trigger custom event
    $(document).trigger("topUpAgent:disconnected", [reason]);
  }

  /**
   * Handle connection error
   */
  function handleConnectError(error) {
    debug("Connection error:", error);
    isConnecting = false;
    connectionAttempts++;
    updateConnectionStatus("error");

    if (connectionAttempts >= maxConnectionAttempts) {
      debug("Max connection attempts reached");
      updateConnectionStatus("failed");
    }
  }

  /**
   * Handle reconnection
   */
  function handleReconnect(attemptNumber) {
    debug("Reconnected after", attemptNumber, "attempts");
    connectionAttempts = 0;
    updateConnectionStatus("connected");
  }

  /**
   * Handle reconnection error
   */
  function handleReconnectError(error) {
    debug("Reconnection error:", error);
  }

  /**
   * Handle reconnection failure
   */
  function handleReconnectFailed() {
    debug("Reconnection failed");
    updateConnectionStatus("failed");
  }

  /**
   * Update connection status in UI
   */
  function updateConnectionStatus(status) {
    const statusElements = $(".connection-status, .websocket-status");
    const statusText = {
      connected: "Connected",
      disconnected: "Disconnected",
      connecting: "Connecting...",
      error: "Connection Error",
      failed: "Connection Failed",
    };

    statusElements
      .removeClass("connected disconnected connecting error failed")
      .addClass(status)
      .text(statusText[status] || status);

    // Update any status indicators
    $(".status-indicator")
      .removeClass("online offline pending")
      .addClass(status === "connected" ? "online" : "offline");
  }

  /**
   * Request server status
   */
  function requestServerStatus() {
    if (socket && socket.connected) {
      socket.emit("get_status");
    }
  }

  /**
   * Handle queue updates
   */
  function handleQueueUpdate(data) {
    debug("Queue update received:", data);

    // Update queue display
    updateQueueDisplay(data);

    // Update metrics
    updateMetrics(data);

    // Trigger custom event
    $(document).trigger("topUpAgent:queueUpdate", [data]);
  }

  /**
   * Handle automation progress
   */
  function handleAutomationProgress(data) {
    debug("Automation progress:", data);

    // Update progress bars
    updateProgressBars(data);

    // Update status displays
    updateAutomationStatus(data);

    // Trigger custom event
    $(document).trigger("topUpAgent:automationProgress", [data]);
  }

  /**
   * Handle job updates from server (main event handler)
   */
  function handleJobUpdate(data) {
    debug("Job update received:", data);

    const eventType = data.type;
    const jobData = data.job;

    if (!eventType || !jobData) {
      debug("Invalid job update data:", data);
      return;
    }

    // Handle different job event types
    switch (eventType) {
      case "started":
        debug("Job started:", jobData);
        updateAutomationStatus({ id: jobData.requestId, status: "processing" });
        showNotification(
          `Automation started for job ${jobData.requestId}`,
          "info"
        );
        break;

      case "completed":
        debug("Job completed:", jobData);
        handleAutomationComplete(jobData);
        break;

      case "failed":
        debug("Job failed:", jobData);
        handleAutomationError(jobData);
        break;

      case "cancelled":
        debug("Job cancelled:", jobData);
        updateAutomationStatus({ id: jobData.requestId, status: "cancelled" });
        showNotification(
          `Automation cancelled for job ${jobData.requestId}`,
          "warning"
        );
        updateWordPressOrderStatus(
          jobData,
          "failed",
          "Automation was cancelled"
        );
        break;
    }

    // Trigger custom event for all job updates
    $(document).trigger("topUpAgent:jobUpdate", [data]);
  }

  /**
   * Handle automation completion
   */
  function handleAutomationComplete(data) {
    debug("Automation complete:", data);

    // Update UI elements
    updateAutomationStatus(data);

    // Show notification
    showNotification("Automation completed successfully", "success");

    // Update WordPress order status via AJAX
    updateWordPressOrderStatus(data, "completed");

    // Trigger custom event
    $(document).trigger("topUpAgent:automationComplete", [data]);
  }

  /**
   * Handle automation error
   */
  function handleAutomationError(data) {
    debug("Automation error:", data);

    // Update UI elements
    updateAutomationStatus(data);

    // Show error notification
    showNotification(
      "Automation failed: " + (data.error || "Unknown error"),
      "error"
    );

    // Update WordPress order status via AJAX
    updateWordPressOrderStatus(data, "failed", data.error || "Unknown error");

    // Trigger custom event
    $(document).trigger("topUpAgent:automationError", [data]);
  }

  /**
   * Handle server status updates
   */
  function handleServerStatus(data) {
    debug("Server status:", data);

    // Update server metrics
    updateServerMetrics(data);

    // Trigger custom event
    $(document).trigger("topUpAgent:serverStatus", [data]);
  }

  /**
   * Handle log messages
   */
  function handleLogMessage(data) {
    debug("Log message:", data);

    // Add to log console
    addLogMessage(data);

    // Trigger custom event
    $(document).trigger("topUpAgent:logMessage", [data]);
  }

  /**
   * Update queue display
   */
  function updateQueueDisplay(queueData) {
    const queueContainer = $(".queue-display");
    if (!queueContainer.length) return;

    let html = "";
    if (queueData.items && queueData.items.length > 0) {
      queueData.items.forEach((item) => {
        html += `
                    <div class="queue-item" data-id="${item.id}">
                        <div class="queue-item-info">
                            <div class="queue-item-id">#${item.id}</div>
                            <div class="queue-item-details">${
                              item.type || "Unknown"
                            } - ${item.created_at || ""}</div>
                        </div>
                        <div class="queue-item-status ${
                          item.status || "pending"
                        }">${item.status || "pending"}</div>
                    </div>
                `;
      });
    } else {
      html =
        '<div class="queue-item"><div class="queue-item-info">No items in queue</div></div>';
    }

    queueContainer.html(html);
  }

  /**
   * Update metrics display
   */
  function updateMetrics(data) {
    if (data.total !== undefined) {
      $('.metric-value[data-metric="total"]').text(data.total);
    }
    if (data.pending !== undefined) {
      $('.metric-value[data-metric="pending"]').text(data.pending);
    }
    if (data.processing !== undefined) {
      $('.metric-value[data-metric="processing"]').text(data.processing);
    }
    if (data.completed !== undefined) {
      $('.metric-value[data-metric="completed"]').text(data.completed);
    }
  }

  /**
   * Update progress bars
   */
  function updateProgressBars(data) {
    if (data.progress !== undefined) {
      const progressBars = $(`.automation-progress-bar[data-id="${data.id}"]`);
      progressBars.css("width", data.progress + "%");

      if (data.status === "completed") {
        progressBars.addClass("completed");
      } else if (data.status === "failed") {
        progressBars.addClass("failed");
      }
    }
  }

  /**
   * Update automation status
   */
  function updateAutomationStatus(data) {
    if (data.id && data.status) {
      const statusElements = $(`.automation-status[data-id="${data.id}"]`);
      statusElements
        .removeClass("pending processing completed failed cancelled")
        .addClass(data.status)
        .text(data.status);
    }
  }

  /**
   * Update server metrics
   */
  function updateServerMetrics(data) {
    // Update various server metrics in the UI
    if (data.cpu !== undefined) {
      $('.metric-value[data-metric="cpu"]').text(data.cpu + "%");
    }
    if (data.memory !== undefined) {
      $('.metric-value[data-metric="memory"]').text(data.memory + "%");
    }
    if (data.uptime !== undefined) {
      $('.metric-value[data-metric="uptime"]').text(formatUptime(data.uptime));
    }
  }

  /**
   * Add log message to console
   */
  function addLogMessage(data) {
    const logConsole = $(".log-console");
    if (!logConsole.length) return;

    const timestamp = new Date().toLocaleTimeString();
    const level = data.level || "INFO";
    const message = data.message || "";

    const logEntry = `
            <div class="log-entry">
                <span class="log-timestamp">[${timestamp}]</span>
                <span class="log-level ${level}">${level}</span>
                <span class="log-message">${message}</span>
            </div>
        `;

    logConsole.append(logEntry);

    // Auto-scroll to bottom
    logConsole.scrollTop(logConsole[0].scrollHeight);

    // Limit log entries to prevent memory issues
    const entries = logConsole.find(".log-entry");
    if (entries.length > 1000) {
      entries.slice(0, 500).remove();
    }
  }

  /**
   * Show notification
   */
  function showNotification(message, type = "info") {
    const notification = $(`
            <div class="notification ${type}">
                ${message}
            </div>
        `);

    // Find or create notification container
    let container = $(".notification-container");
    if (!container.length) {
      container = $('<div class="notification-container"></div>');
      $("body").append(container);
    }

    container.append(notification);

    // Auto-remove after 5 seconds
    setTimeout(() => {
      notification.fadeOut(() => notification.remove());
    }, 5000);
  }

  /**
   * Format uptime
   */
  function formatUptime(seconds) {
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    if (days > 0) {
      return `${days}d ${hours}h`;
    } else if (hours > 0) {
      return `${hours}h ${minutes}m`;
    } else {
      return `${minutes}m`;
    }
  }

  /**
   * Public API
   */
  window.TopUpAgentSocket = {
    connect: initializeSocket,
    disconnect: function () {
      if (socket) {
        socket.disconnect();
      }
    },
    emit: function (event, data) {
      if (socket && socket.connected) {
        socket.emit(event, data);
      }
    },
    isConnected: function () {
      return socket && socket.connected;
    },
    getSocket: function () {
      return socket;
    },
  };

  /**
   * Initialize when document is ready
   */
  $(document).ready(function () {
    debug("Document ready, initializing Socket.IO");

    // Add connection status indicator if it doesn't exist
    if (!$(".websocket-status").length && isAdmin) {
      $("body").append(
        '<div class="websocket-status connecting">Connecting...</div>'
      );
    }

    // Initialize connection
    initializeSocket();

    // Setup UI event handlers
    setupUIHandlers();
  });

  /**
   * Setup UI event handlers
   */
  function setupUIHandlers() {
    // Queue control buttons
    $(document).on("click", ".queue-start-btn", function () {
      if (socket && socket.connected) {
        socket.emit("start_queue");
        showNotification("Queue started", "success");
      }
    });

    $(document).on("click", ".queue-stop-btn", function () {
      if (socket && socket.connected) {
        socket.emit("stop_queue");
        showNotification("Queue stopped", "warning");
      }
    });

    $(document).on("click", ".queue-clear-btn", function () {
      if (confirm("Are you sure you want to clear the queue?")) {
        if (socket && socket.connected) {
          socket.emit("clear_queue");
          showNotification("Queue cleared", "info");
        }
      }
    });

    // Refresh button
    $(document).on("click", ".refresh-btn", function () {
      requestServerStatus();
      if (socket && socket.connected) {
        socket.emit("get_queue_status");
      }
    });

    // Manual reconnect button
    $(document).on("click", ".reconnect-btn", function () {
      if (socket) {
        socket.disconnect();
        setTimeout(initializeSocket, 1000);
      }
    });
  }

  /**
   * Update WordPress order status via AJAX
   */
  function updateWordPressOrderStatus(data, status, message = "") {
    // Extract order ID from various possible data structures
    let orderId = data.orderId || data.order_id || data.orderId || null;

    // For job data, look for nested queue information
    if (!orderId && data.queue) {
      orderId = data.queue.order_id || data.queue.orderId;
    }

    // If still no order ID and we have a queue ID, try to find it from stored mappings
    if (!orderId && (data.queueId || data.id)) {
      orderId = findOrderIdByQueueId(data.queueId || data.id);
    }

    if (!orderId) {
      debug("Cannot update order status: No order ID found in data", data);
      // Don't show error notification as this might be normal for some automations
      return;
    }

    // Prepare status message
    let statusMessage = message;
    if (!statusMessage) {
      if (status === "completed") {
        statusMessage = "✅ Automation completed successfully via WebSocket";
      } else if (status === "failed") {
        statusMessage = "❌ Automation failed via WebSocket";
      } else {
        statusMessage = `🔄 Automation status: ${status}`;
      }
    }

    debug(`Updating WordPress order #${orderId} status to: ${status}`);

    // Make AJAX call to WordPress
    $.ajax({
      url: config.ajaxUrl || "/wp-admin/admin-ajax.php",
      type: "POST",
      data: {
        action: "websocket_automation_update",
        order_id: orderId,
        status: status,
        message: statusMessage,
        nonce: config.nonce,
        queue_id: data.queueId || data.id,
        player_id: data.playerId || data.player_id,
        license_key:
          data.licenseKey || data.license_key || data.redimension_code,
      },
      success: function (response) {
        debug("WordPress order status updated:", response);
        if (response.success) {
          showNotification(
            `Order #${orderId} status updated to ${status}`,
            "success"
          );

          // Refresh the order page if we're on an order page
          if (
            window.location.href.includes("post.php") ||
            window.location.href.includes("order-received")
          ) {
            setTimeout(() => {
              window.location.reload();
            }, 2000);
          }
        } else {
          console.error("Failed to update order status:", response.data);
          showNotification(
            "Failed to update order status in WordPress",
            "error"
          );
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX error updating order status:", error);
        showNotification("Error communicating with WordPress", "error");
      },
    });
  }

  /**
   * Find order ID by queue ID (simple mapping - could be improved with cache)
   */
  function findOrderIdByQueueId(queueId) {
    // This is a simple implementation - you might want to improve this
    // by maintaining a mapping of queue IDs to order IDs

    // For now, we'll return null and rely on the server to include orderId in the data
    return null;
  }
})(jQuery);
