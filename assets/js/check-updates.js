/**
 * WP GitHub Updater - Check Updates Script
 *
 * Generic update checker that works with any plugin using wp-github-updater.
 * Receives configuration via wp_localize_script.
 *
 * @package SilverAssist\WpGithubUpdater
 * @version 1.3.0
 * @since 1.3.0
 */
(($) => {
	"use strict";

	/**
	 * Show WordPress admin notice
	 *
	 * @param {string} pluginName - Plugin display name for the notice prefix
	 * @param {string} message    - The message to display
	 * @param {string} type       - Notice type: 'success', 'error', 'warning', 'info'
	 */
	const showAdminNotice = (pluginName, message, type = "info") => {
		const noticeClass = `notice notice-${type} is-dismissible`;
		const noticeHtml = `
			<div class="${noticeClass}" style="margin: 15px 0;">
				<p><strong>${pluginName}:</strong> ${message}</p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text">Dismiss this notice.</span>
				</button>
			</div>
		`;

		const $notice = $(noticeHtml);
		$("h1").first().after($notice);

		$notice.find(".notice-dismiss").on("click", function () {
			$notice.fadeOut(300, function () {
				$(this).remove();
			});
		});

		if (type === "success" || type === "info") {
			setTimeout(() => {
				$notice.fadeOut(300, function () {
					$(this).remove();
				});
			}, 5000);
		}
	};

	/**
	 * Factory: creates a check-updates function for a specific plugin.
	 * Each plugin gets its own localized data object keyed by a unique handle.
	 *
	 * @param {string} dataKey - The global variable name set by wp_localize_script
	 * @returns {void}
	 */
	window.wpGithubUpdaterCheckUpdates = function (dataKey) {
		const config = window[dataKey] || {};
		const { ajaxurl, nonce, updateUrl, action, pluginName, strings = {} } = config;

		if (!ajaxurl || !nonce || !action) {
			console.error("WP GitHub Updater: configuration missing for", dataKey);
			showAdminNotice(
				pluginName || "Plugin",
				strings.configError || "Update check configuration error.",
				"error"
			);
			return;
		}

		showAdminNotice(
			pluginName,
			strings.checking || "Checking for updates...",
			"info"
		);

		$.ajax({
			url: ajaxurl,
			type: "POST",
			data: { action, nonce },
			success(response) {
				if (response.success) {
					if (response.data?.update_available) {
						const msg =
							strings.updateAvailable?.replace(
								"%s",
								response.data.new_version || response.data.latest_version
							) || "Update available! Redirecting to Updates page...";
						showAdminNotice(pluginName, msg, "warning");
						setTimeout(() => {
							window.location.href = updateUrl;
						}, 2000);
					} else {
						showAdminNotice(
							pluginName,
							strings.upToDate || "You're up to date!",
							"success"
						);
					}
				} else {
					showAdminNotice(
						pluginName,
						response.data?.message ||
							strings.checkError ||
							"Error checking updates.",
						"error"
					);
				}
			},
			error() {
				showAdminNotice(
					pluginName,
					strings.connectError || "Error connecting to update server.",
					"error"
				);
			},
		});
	};
})(jQuery);
