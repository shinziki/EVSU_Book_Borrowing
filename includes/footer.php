            </main>
        </div>
    </div>

    <script>
        // Initialize barcodes on page load
        document.addEventListener('DOMContentLoaded', () => {
            // Generate barcodes
            document.querySelectorAll('svg[jsbarcode-value]').forEach(svg => {
                JsBarcode(svg, svg.getAttribute('jsbarcode-value'), {
                    format: svg.getAttribute('jsbarcode-format') || 'CODE128',
                    displayValue: svg.getAttribute('jsbarcode-displayvalue') !== 'false',
                    text: svg.getAttribute('jsbarcode-text') || undefined,
                    textAlign: svg.getAttribute('jsbarcode-textalign') || 'center',
                    textPosition: svg.getAttribute('jsbarcode-textposition') || 'bottom',
                    textMargin: parseInt(svg.getAttribute('jsbarcode-textmargin')) || 2,
                    fontSize: parseInt(svg.getAttribute('jsbarcode-fontsize')) || 20,
                    fontOptions: svg.getAttribute('jsbarcode-fontoptions') || '',
                    background: svg.getAttribute('jsbarcode-background') || '#ffffff',
                    lineColor: svg.getAttribute('jsbarcode-linecolor') || '#000000',
                    width: parseInt(svg.getAttribute('jsbarcode-width')) || 2,
                    height: parseInt(svg.getAttribute('jsbarcode-height')) || 100,
                    margin: parseInt(svg.getAttribute('jsbarcode-margin')) || 10
                });
            });

            // Add data-label attributes to tables for better mobile display
            document.querySelectorAll('table.responsive-table').forEach(table => {
                const headerCells = table.querySelectorAll('thead th');
                const headerTexts = Array.from(headerCells).map(th => th.textContent.trim());
                
                table.querySelectorAll('tbody tr').forEach(row => {
                    const cells = row.querySelectorAll('td');
                    cells.forEach((cell, index) => {
                        if (headerTexts[index]) {
                            cell.setAttribute('data-label', headerTexts[index]);
                        }
                    });
                });
            });
        });

        // Theme toggle functionality
        const themeToggle = document.getElementById('theme-toggle');
        themeToggle.addEventListener('click', () => {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                document.cookie = "theme=light; path=/; max-age=31536000"; // 1 year
            } else {
                document.documentElement.classList.add('dark');
                document.cookie = "theme=dark; path=/; max-age=31536000"; // 1 year
            }
        });

        // Mobile menu toggle
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        
        // Toggle sidebar open/close
        function toggleSidebar() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.classList.toggle('overflow-hidden');
        }
        
        menuToggle.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);
        
        // Close sidebar when clicking on a link (mobile only)
        document.querySelectorAll('#sidebar a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 768) {
                    toggleSidebar();
                }
            });
        });

        // Close sidebar when window is resized to desktop size
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768 && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                document.body.classList.remove('overflow-hidden');
            }
        });

        // Handle responsive tables
        // Add this attribute to maintain backwards compatibility
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('table:not(.responsive-table)').forEach(table => {
                // Add the class to tables that don't have it yet
                if (table.closest('.overflow-x-auto') === null) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'overflow-x-auto';
                    table.parentNode.insertBefore(wrapper, table);
                    wrapper.appendChild(table);
                }
            });
        });

        // Auto-focus for barcode input fields
        if (document.querySelector('input[name="book_barcode"]')) {
            document.querySelector('input[name="book_barcode"]').focus();
        }
    </script>
</body>
</html> 