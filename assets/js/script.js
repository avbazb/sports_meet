/**
 * 运动会管理系统全局JavaScript文件
 */

document.addEventListener('DOMContentLoaded', function() {
    // 初始化所有模态框
    initModals();
    
    // 初始化标签页
    initTabs();
    
    // 初始化确认删除
    initDeleteConfirmation();
});

/**
 * 初始化模态框
 */
function initModals() {
    // 打开模态框按钮
    const modalTriggers = document.querySelectorAll('[data-toggle="modal"]');
    
    modalTriggers.forEach(function(trigger) {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-target');
            const modal = document.getElementById(targetId);
            
            if (modal) {
                openModal(modal);
            }
        });
    });
    
    // 关闭模态框按钮
    const closeButtons = document.querySelectorAll('.modal-close, [data-dismiss="modal"]');
    
    closeButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            closeModal(modal);
        });
    });
    
    // 点击模态框外部关闭
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeModal(e.target);
        }
    });
}

/**
 * 打开模态框
 * @param {HTMLElement} modal 模态框元素
 */
function openModal(modal) {
    document.body.style.overflow = 'hidden'; // 防止背景滚动
    modal.style.display = 'block';
}

/**
 * 关闭模态框
 * @param {HTMLElement} modal 模态框元素
 */
function closeModal(modal) {
    document.body.style.overflow = '';
    modal.style.display = 'none';
}

/**
 * 初始化标签页
 */
function initTabs() {
    const tabs = document.querySelectorAll('.tab');
    
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            // 获取标签组
            const tabContainer = this.closest('.tabs');
            const allTabs = tabContainer.querySelectorAll('.tab');
            
            // 移除所有标签的激活状态
            allTabs.forEach(function(t) {
                t.classList.remove('active');
            });
            
            // 添加当前标签的激活状态
            this.classList.add('active');
            
            // 获取标签对应的内容ID
            const tabId = this.getAttribute('data-tab');
            
            // 获取所有内容
            const allContents = document.querySelectorAll('.tab-content');
            
            // 隐藏所有内容
            allContents.forEach(function(content) {
                content.classList.remove('active');
            });
            
            // 显示当前标签对应的内容
            const activeContent = document.getElementById(tabId + '-content');
            if (activeContent) {
                activeContent.classList.add('active');
            }
        });
    });
}

/**
 * 初始化删除确认
 */
function initDeleteConfirmation() {
    const deleteButtons = document.querySelectorAll('[data-confirm]');
    
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm');
            
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
}

/**
 * 显示加载中指示器
 * @param {HTMLElement} container 容器元素
 * @param {string} message 显示消息
 */
function showLoader(container, message = '加载中...') {
    container.innerHTML = `
        <div class="text-center p-5">
            <div class="loader"></div>
            <p class="mt-3">${message}</p>
        </div>
    `;
}

/**
 * 显示警告信息
 * @param {string} message 消息内容
 * @param {string} type 消息类型 (success, danger, warning)
 * @param {HTMLElement} container 容器元素
 */
function showAlert(message, type = 'success', container = null) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    // 添加关闭按钮
    const closeBtn = document.createElement('span');
    closeBtn.className = 'close';
    closeBtn.innerHTML = '&times;';
    closeBtn.style.float = 'right';
    closeBtn.style.cursor = 'pointer';
    closeBtn.onclick = function() {
        alertDiv.remove();
    };
    
    alertDiv.prepend(closeBtn);
    
    if (container) {
        // 插入到指定容器
        container.prepend(alertDiv);
    } else {
        // 插入到页面顶部
        const firstChild = document.body.firstChild;
        document.body.insertBefore(alertDiv, firstChild);
    }
    
    // 5秒后自动关闭
    setTimeout(function() {
        alertDiv.remove();
    }, 5000);
}

/**
 * 格式化日期时间
 * @param {string|Date} dateTime 日期时间
 * @param {string} format 格式 (datetime, date, time)
 * @returns {string} 格式化后的日期时间
 */
function formatDateTime(dateTime, format = 'datetime') {
    const date = new Date(dateTime);
    
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    
    if (format === 'date') {
        return `${year}-${month}-${day}`;
    } else if (format === 'time') {
        return `${hours}:${minutes}`;
    } else {
        return `${year}-${month}-${day} ${hours}:${minutes}`;
    }
}

/**
 * 发送AJAX请求
 * @param {string} url 请求URL
 * @param {string} method 请求方法 (GET, POST)
 * @param {Object} data 请求数据
 * @param {Function} callback 回调函数
 */
function ajax(url, method = 'GET', data = null, callback = null) {
    const xhr = new XMLHttpRequest();
    
    xhr.open(method, url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                let response = xhr.responseText;
                
                try {
                    response = JSON.parse(response);
                } catch (e) {
                    // 不是JSON，保持原样
                }
                
                if (callback) {
                    callback(null, response);
                }
            } else {
                if (callback) {
                    callback(new Error('请求失败: ' + xhr.status), null);
                }
            }
        }
    };
    
    let requestData = null;
    
    if (data) {
        if (typeof data === 'object') {
            const params = [];
            
            for (let key in data) {
                if (data.hasOwnProperty(key)) {
                    params.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
                }
            }
            
            requestData = params.join('&');
        } else {
            requestData = data;
        }
    }
    
    xhr.send(requestData);
}

/**
 * 批量添加
 * @param {string} target 目标（比如 'participants'）
 */
function batchAdd(target) {
    const modal = document.getElementById('batchAddModal');
    const modalTitle = modal.querySelector('.modal-title');
    const form = modal.querySelector('form');
    const textarea = form.querySelector('textarea');
    
    // 设置标题和表单目标
    switch (target) {
        case 'events':
            modalTitle.textContent = '批量添加赛事';
            textarea.placeholder = '格式：比赛项目 人数 组数 比赛时间\n例如：初二女子100米预赛 28 4 9:00';
            form.action = 'batch_add_events.php';
            break;
            
        case 'participants':
            modalTitle.textContent = '批量添加参赛人员';
            textarea.placeholder = '格式：姓名 性别 班级\n例如：张三 男 初二(1)班';
            form.action = 'batch_add_participants.php';
            break;
            
        case 'results':
            modalTitle.textContent = '批量添加成绩';
            textarea.placeholder = '格式：赛事ID 参赛者ID 成绩 名次\n例如：1 5 12.5 1';
            form.action = 'batch_add_results.php';
            break;
    }
    
    // 清空之前的输入
    textarea.value = '';
    
    // 显示模态框
    openModal(modal);
} 