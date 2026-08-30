<?php

namespace Modules\Shop\Controllers;

use System\Engine\Controller;
use System\Engine\Registry;

class Base extends Controller {
    public function __construct(Registry $registry) {
        parent::__construct($registry);

        $userId = $this->auth->data('id');

        $cart_head = "<div class='position-relative'>";
        $cart_head .= "<a href='".$this->url->to('shop/cart')."' class='nav-link position-relative d-inline-block'>";
        $cart_head .= "<i class='fa-solid fa-cart-shopping text-white fs-4'></i>";
        $cart_head .= "<span id='cart-count' class='position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary' style='font-size:0.75rem; padding:4px 6px;'>0</span>";
        $cart_head .= "</a>";
        $cart_head .= "</div>";

        $this->view->assign('head_title', $cart_head);

        $this->doc->addInlineJS('
            function updateCartCount() {
                $.get("' . $this->url->to('shop/cart/count') . '", function(count) {
                    $("#cart-count").text(count);
                });
            }

            $(document).ready(function() {
                $("#add-to-cart-form").on("submit", function(e) {
                    e.preventDefault();
                    $.ajax({
                        url: "' . $this->url->to('shop/cart/add') . '",
                        method: "POST",
                        data: $(this).serialize(),
                        dataType: "json",
                        success: function(response) {
                            if (response.status === "success") {
                                $("#cart-notice").html(\'<div class="alert alert-success">\' + response.message + \'</div>\');
                                updateCartCount();
                            } else {
                                $("#cart-notice").html(\'<div class="alert alert-danger">\' + response.message + \'</div>\');
                            }
                        }
                    });
                });

                updateCartCount();
            });
        ');
    }
}