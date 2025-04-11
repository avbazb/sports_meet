        </main>
    </div>
    
    <script>
    // 通用JS函数
    document.addEventListener('DOMContentLoaded', function() {
        // 自动隐藏消息提示
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            }, 5000);
        });
        
        // 模态框处理
        const modalTriggers = document.querySelectorAll('[data-toggle="modal"]');
        modalTriggers.forEach(function(trigger) {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('data-target');
                // 支持带#和不带#的两种写法
                const modalId = targetId.startsWith('#') ? targetId : '#' + targetId;
                const modal = document.querySelector(modalId);
                
                if (modal) {
                    // 直接使用display: block而不是class
                    modal.style.display = 'block';
                    console.log('打开模态框:', modalId);
                } else {
                    console.error('未找到模态框:', modalId);
                }
            });
        });
        
        // 关闭按钮处理
        const modalCloses = document.querySelectorAll('.modal-close, .modal .close-btn');
        modalCloses.forEach(function(close) {
            close.addEventListener('click', function(e) {
                e.preventDefault();
                const modal = this.closest('.modal');
                if (modal) {
                    modal.style.display = 'none';
                }
            });
        });
        
        // 点击模态框背景关闭
        const modals = document.querySelectorAll('.modal');
        modals.forEach(function(modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });

        // 为打开模态框提供全局函数
        window.openModal = function(modalId) {
            const modal = typeof modalId === 'string' 
                ? document.getElementById(modalId.replace('#', '')) 
                : modalId;
            if (modal) {
                modal.style.display = 'block';
            }
        };

        // 为关闭模态框提供全局函数
        window.closeModal = function(modalId) {
            const modal = typeof modalId === 'string' 
                ? document.getElementById(modalId.replace('#', '')) 
                : modalId;
            if (modal) {
                modal.style.display = 'none';
            }
        };
    });
    </script>
    
    <?php if (isset($pageScript)): ?>
    <script><?php echo $pageScript; ?></script>
    <?php endif; ?>
</body>
</html> 