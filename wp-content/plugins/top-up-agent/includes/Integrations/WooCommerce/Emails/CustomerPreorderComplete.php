<?php

namespace TopUpAgent\Integrations\WooCommerce\Emails;

use WC_Email;
use WC_Order;

defined('ABSPATH') || exit;

class CustomerPreorderComplete extends WC_Email
{
    /**
     * CustomerPreorderComplete constructor.
     */
    function __construct()
    {
        // Email slug we can use to filter other data.
        $this->id          = 'tua_email_customer_preorder_complete';
        $this->title       = __('Completed preorder', 'top-up-agent');
        $this->description = __('An email sent to the customer when a license key preorder is complete.', 'top-up-agent');

        // For admin area to let the user know we are sending this email to customers.
        $this->customer_email = true;
        $this->heading        = __('Preorder complete', 'top-up-agent');

       
        $this->subject = sprintf(
            // translators: placeholder is {blogname}, a variable that will be substituted when email is sent out
            _x(
                '[%s] - Your preorder has arrived!',
                'Default email subject for completed pre-order emails sent to the customer',
                'top-up-agent'
            ),
            '{blogname}'
        );

        // Template paths.
        $this->template_html  = 'emails/tua-email-customer-preorder-complete.php';
        $this->template_plain = 'emails/plain/tua-email-customer-preorder-complete.php';
        $this->template_base  = TUA_TEMPLATES_DIR;

        // Action to which we hook onto to send the email.
        add_action('tua_email_customer_preorder_complete', array($this, 'trigger'));

        parent::__construct();
    }

    /**
     * Retrieves the HTML content of the email.
     *
     * @return string
     */
    public function get_content_html()
    {
        return wc_get_template_html(
            $this->template_html,
            array(
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text'    => false,
                'email'         => $this
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Retrieves the plain text content of the email.
     *
     * @return string
     */
    public function get_content_plain()
    {
        return wc_get_template_html(
            $this->template_plain,
            array(
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text'    => true,
                'email'         => $this
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Trigger the sending of this email.
     *
     * @param int           $orderId WooCommerce order ID
     * @param WC_Order|bool $order   WooCommerce order, or a false flag
     */
    public function trigger($orderId, $order = false)
    {
        $this->setup_locale();

        if ($orderId && ! is_a($order, 'WC_Order')) {
            $order = wc_get_order($orderId);
        }

        if (is_a($order, 'WC_Order')) {
            $this->object                         = $order;
            $this->recipient                      = $this->object->get_billing_email();
            $this->placeholders['{order_date}']   = wc_format_datetime($this->object->get_date_created());
            $this->placeholders['{order_number}'] = $this->object->get_order_number();
        }

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send(
                $this->get_recipient(),
                $this->get_subject(),
                $this->get_content(),
                $this->get_headers(),
                $this->get_attachments()
            );
        }

        $this->restore_locale();
    }
}
