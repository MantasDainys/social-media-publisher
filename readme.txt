=== Social Media Publisher ===
Contributors: mantasdainys
Tags: facebook auto publish, social media publish, post to facebook, facebook integration, auto post to Facebook, WordPress Facebook plugin
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Social Media Publisher automates sharing WP posts to your Facebook page, streamlining the process and saving you valuable time.

== Description ==
The Social Media Publisher plugin automates sharing WP posts to your Facebook page via the API, saving time and boosting audience reach.

### Key Features:
- Automatically share WP posts to your Facebook page.
- Customize post content before publishing.
- Manage publishing preferences directly from WP.
- Increase engagement and audience reach effortlessly.

Whether you're managing a personal blog or a business website, Social Media Publisher is designed to simplify your social media strategy and improve your workflow.

== Installation ==

1. Install the plugin:
   - Option 1: **Manual Installation**
     - Upload the `social-media-publisher` folder to the `/wp-content/plugins/` directory using an FTP client or File Manager.
   - Option 2: **Installation via WordPress Dashboard**
     - Go to your WordPress Dashboard.
     - Navigate to **Plugins** > **Add New**.
     - Click on the **Upload Plugin** button at the top of the page.
     - Select the `social-media-publisher.zip` file from your computer and click **Install Now**.

2. Activate the plugin through the 'Plugins' menu in WordPress.

== Configuration ==

3. Configure your Facebook API credentials:
   - Log in to your [Facebook Developer account](https://developers.facebook.com/).
   - Create a new App if you have not already done so.
   - Navigate to the App's **Settings** and retrieve your **App ID** and **App Secret**.
   - Use the [Facebook Graph API Explorer](https://developers.facebook.com/tools/explorer/) to generate a **Page Access Token**:
     - Select your App from the dropdown menu in the Graph API Explorer.
     - Request the following permissions to generate the token:
       - **pages_show_list**
       - **pages_read_engagement**
       - **pages_manage_posts**
     - Once you have generated the token, copy it and save it securely.
   - Note down your **App ID**, **App Secret**, **Page Access Token**, and your Facebook **Page ID**.

4. Enter your Facebook API credentials into the plugin:
   - Go to your WordPress Dashboard.
   - Navigate to the following URL: **[your-site-url]/wp-admin/options.php**.
   - Scroll down to find the fields for `wpsmp_app_id`, `wpsmp_app_secret`, `wpsmp_page_access_token`, and `wpsmp_page_id`.
   - Fill in these fields with the corresponding values:
     - **wpsmp_app_id**: Your Facebook App ID.
     - **wpsmp_app_secret**: Your Facebook App Secret.
     - **wpsmp_page_access_token**: Your Facebook Page Access Token.
     - **wpsmp_page_id**: Your Facebook Page ID.
   - After entering the credentials, scroll to the bottom of the page and click **Save Changes**.

== Usage ==

5. Once the plugin is configured, you can start publishing posts directly to your Facebook page:
   - Create or edit a WordPress post.
   - Check the box to enable Facebook publishing for the post.
   - Publish or update the post, and it will be shared to your Facebook page automatically.

== Troubleshooting ==

- If posts do not publish to Facebook:
  - Verify your Facebook API credentials and ensure they are correct.
  - Check that your Facebook App has the necessary permissions:
    - **pages_show_list**
    - **pages_read_engagement**
    - **pages_manage_posts**
  - Ensure that your server has outgoing access to Facebook's API.
  - If an error occurs during publishing, a notification will appear at the top of the admin screen with a specific error message. This message will help you identify and resolve the issue.
  - If no errors are shown, check your server logs for any related issues or contact your hosting provider for assistance.

== Frequently Asked Questions ==
= Do I need a Facebook App for this plugin? =
Yes, you need to create a Facebook App and configure its credentials in the plugin settings.

= Can I customize what is published on Facebook? =
Yes, the plugin allows you to customize the content shared on Facebook, including post text and images.

= Is this plugin free to use? =
Yes, this plugin is completely free and open-source.

== Changelog ==
= 1.0.2 =
* Initial release of the plugin.