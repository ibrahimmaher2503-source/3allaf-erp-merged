<script>
    function labelWorkspace(initial, endpoint, priceEndpoint) {
        return {
            query: '',
            results: [],
            selected: initial,
            nextPage: null,
            loading: false,
            error: '',
            selectedStoreId: '',
            priceRequest: 0,

            init() {
                this.selected = this.selected.map((product) => ({
                    ...product,
                    copies: String(product.copies || 1),
                    barcode_id: product.barcode_id || this.primary(product)?.id || '',
                }));
                this.$nextTick(() => {
                    this.selectedStoreId = this.$el.querySelector('[name="store_id"]')?.value || '';
                    this.refreshPrices(this.selectedStoreId);
                });
            },

            async refreshPrices(storeId) {
                this.selectedStoreId = storeId;
                this.error = '';
                const requestId = ++this.priceRequest;
                this.selected.forEach((product) => { product.resolved_price = null; });
                if (!storeId || !this.selected.length) return;
                const params = new URLSearchParams({ store_id: storeId });
                this.selected.forEach((product) => params.append('product_ids[]', String(product.id)));
                try {
                    const response = await fetch(priceEndpoint + '?' + params, { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error('Price lookup failed');
                    const prices = (await response.json()).prices;
                    if (requestId !== this.priceRequest) return;
                    this.selected.forEach((product) => { product.resolved_price = prices[product.id] || null; });
                } catch (error) {
                    if (requestId === this.priceRequest) this.error = @js(str_starts_with(app()->getLocale(), 'ar') ? 'تعذر تحميل سعر البيع لهذا المنفذ. حاول مرة أخرى.' : 'Selling prices could not be loaded for this outlet. Try again.');
                }
            },

            name(product) {
                return document.documentElement.lang.startsWith('ar')
                    ? product.name_ar
                    : (product.name_en || product.name_ar);
            },

            primary(product) {
                return (product.barcodes || []).find((barcode) => barcode.is_primary)
                    || (product.barcodes || [])[0];
            },

            has(id) {
                return this.selected.some((product) => product.id === id);
            },

            select(product) {
                if (!this.has(product.id)) {
                    this.selected.push({
                        ...product,
                        copies: '1',
                        barcode_id: this.primary(product)?.id || '',
                    });
                    this.refreshPrices(this.selectedStoreId);
                }
            },

            remove(id) {
                this.selected = this.selected.filter((product) => product.id !== id);
            },

            async search(exact = false, page = 1, append = false) {
                const query = this.query.trim();

                if (!query) {
                    this.results = [];
                    this.nextPage = null;
                    return;
                }

                this.loading = true;
                this.error = '';

                try {
                    const response = await fetch(
                        endpoint
                            + '?q=' + encodeURIComponent(query)
                            + '&page=' + page
                            + (exact ? '&exact=1' : ''),
                        { headers: { Accept: 'application/json' } },
                    );

                    if (!response.ok) {
                        throw new Error('Search request failed');
                    }

                    const payload = await response.json();
                    this.results = append ? [...this.results, ...payload.data] : payload.data;
                    this.nextPage = payload.next_page;

                    if (exact && payload.data.length === 1) {
                        this.select(payload.data[0]);
                    }
                } catch (error) {
                    this.error = @js(__('Product search could not be completed. Try again.'));
                } finally {
                    this.loading = false;
                }
            },

            more() {
                if (this.nextPage) {
                    this.search(false, this.nextPage, true);
                }
            },
        };
    }
</script>
