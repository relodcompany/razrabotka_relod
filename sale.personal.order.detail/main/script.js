BX.namespace('BX.Sale.PersonalOrderComponent');

(function() {
	BX.Sale.PersonalOrderComponent.PersonalOrderDetail = function(nodeSelector, params) {
		this.init(nodeSelector, params);
	}

	BX.Sale.PersonalOrderComponent.PersonalOrderDetail.prototype = {
		node: null,
		id: 0,

		init : function(nodeSelector, params) {
			this.node = document.querySelector(nodeSelector);

			if (BX.type.isPlainObject(params)) {
				this.id = params.id;
			}

			if (this.node) {
				BX.bindDelegate(
					this.node, 
					'click', 
					{
						className: 'order__info__items-toggle',
					},
					BX.proxy(
						function(event) {
							event = event || window.event;

							let trigger = event.target.closest('.order__info__items-toggle');
							if (trigger) {
								let items = trigger.previousElementSibling;
								if (items) {
									BX.toggleClass(items, 'open');
	
									let bOpened = items.classList.contains('open');
									trigger.textContent = BX.message(bOpened ? 'SPOD_TOGGLE_CLOSE' : 'SPOD_TOGGLE_OPEN');
								}
							}
						}, this
					)
				);

				BX.bindDelegate(
					this.node, 
					'click', 
					{
						className: 'show_on_map',
					},
					BX.proxy(
						function(event) {
							event = event || window.event;

							let trigger = event.target.closest('.show_on_map');
							if (trigger) {
								let block = trigger.closest('.order__info');
								if (block) {
									let map = block.querySelector('.store__map-wrapper');
									if (map) {
										map.classList.toggle('hidden');
									}
								}
							}
						}, this
					)
				);

				BX.bindDelegate(
					this.node, 
					'click', 
					{
						className: 'js-show-shipment-products',
					},
					BX.proxy(
						function(event) {
							event = event || window.event;
							event.preventDefault();

							let trigger = event.target.closest('.js-show-shipment-products');
							if (trigger) {
								let block = trigger.closest('.order__info');
								if (block) {
									let products = block.querySelector('.order__cart__items');
									if (products) {
										products.classList.toggle('hidden');
									}
								}
							}
						}, this
					)
				);

				BX.bindDelegate(
					this.node,
					'click',
					{
						className: 'order__card__button--pay',
					},
					BX.proxy(
						function(event) {
							event = event || window.event;
							event.preventDefault();

							scrollToBlock('#order__payments');
						}, this
					)
				);

				BX.addCustomEvent(
					'onOrderPaymentChange',
					BX.proxy(function(eventdata) {
						if (
							typeof eventdata === 'object' &&
							eventdata
						) {
							if (eventdata.orderId == this.id) {
								let payments = document.getElementById('order__payments');
								if (payments) {
									payments.classList.add('loading-state-before');
								}
								
								if (location.hash !== '#order__payments') {
									let url = location.href.replace(location.hash, '') + '#order__payments';

									if (typeof history !== 'undefined') {
										history.replaceState(null, null, url);
									}
									else {
										location.href = url;
									}
								}
								
								location.reload();
							}
						}
					}, this)
				);

				BX.bindDelegate(
					this.node, 
					'click', 
					{
						className: 'js-pay-payment',
					},
					BX.proxy(
						function(event) {
							event = event || window.event;
							event.preventDefault();

							let payButton = event.target.closest('.js-pay-payment');
							if (payButton) {
								let block = payButton.closest('.order__info');
								if (block) {
									let template = block.querySelector('.order__info__payment-template');
									if (template) {
										payButton.classList.add('loadings');

										let content = template.content.cloneNode(true);
										let tmp = BX.create({
											tag: 'DIV',
										});
										tmp.append(content);

										let trigger = BX.create({
											tag: 'div',
											attrs: {
												'data-event': 'jqm',
												'data-name': 'message',
												'data-param-form_id': 'message',
												'data-param-message_title': encodeURIComponent(payButton.dataset.title || ''),
												'data-param-message_button_title': '',
												'data-param-message_button_class': '',
											},
										});
			
										BX.append(trigger, document.body);
			
										$(trigger).jqmEx(
											BX.proxy(
												function(name, hash, _this) {
													if (payButton) {
														payButton.classList.remove('loadings');
													}
							
													let popup = hash.w[0];
													if (popup) {
														popup.classList.add('popup--order-pay');

														let popupBody = popup.querySelector('.form-body');
														if (popupBody) {
															let obData= BX.processHTML(tmp.innerHTML);
															let html = obData.HTML.trim();
															
															popupBody.innerHTML = html;
															BX.ajax.processScripts(obData.SCRIPT);
														}
													}
												}, this
											),
											BX.proxy(
												function(name, hash, _this) {
													if (payButton) {
														payButton.classList.remove('loadings');
													}
												}, this
											)
										);
										
										// do not click with mobile template
										if (!arAsproOptions.SITE_TEMPLATE_PATH_MOBILE) {
											BX.fireEvent(trigger, 'click');
										}

										trigger.remove();
									}
								}
							}
						}, this
					)
				);
			}
		},
	};
})();

;(function() {
    // === НАЧАЛО ДОБАВЛЕНИЯ ===

    // Открытие модалки
    BX.bindDelegate(
        document.body,
        'click',
        { className: 'order__card__button--edit' },
        function(event) {
            event.preventDefault();
            // Показываем контейнер, который зарегистрирован через SetViewTarget
            var modal = BX('modal-edit-order');
            if (!modal) return;

            modal.style.display = 'block';

            // Подгружаем форму через AJAX
            var orderId = this.id; // в вашем шаблоне data-order-id="<?= (int)$arResult['ID'] ?>"
            BX.showWait();
            BX.ajax.get(
                '/local/ajax/getOrderEditForm.php?orderId=' + orderId,
                function(html) {
                    BX.closeWait();
                    // вставляем в тело модалки
                    var body = BX('js-edit-order-body');
                    if (body) {
                        body.innerHTML = html;
                    }
                }
            );
        }
    );

    // Закрытие модалки по крестику
    BX.bind(
        BX('js-edit-order-close'),
        'click',
        function() {
            var modal = BX('modal-edit-order');
            if (modal) modal.style.display = 'none';
        }
    );

    // Закрытие модалки по кнопке «Отмена»
    BX.bind(
        BX('js-edit-order-cancel'),
        'click',
        function() {
            var modal = BX('modal-edit-order');
            if (modal) modal.style.display = 'none';
        }
    );

    // Клик по оверлею (чтобы по пустому фону тоже закрывалось)
    BX.bindDelegate(
        document.body,
        'click',
        { className: 'lc-modal' },
        function(event) {
            if (event.target === this) {
                this.style.display = 'none';
            }
        }
    );

    // === КОНЕЦ ДОБАВЛЕНИЯ ===
})();
