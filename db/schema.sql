-- 创建运动会管理系统数据库
CREATE DATABASE IF NOT EXISTS sports_meet;
USE sports_meet;

-- 班级表
CREATE TABLE IF NOT EXISTS classes (
    class_id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(50) NOT NULL,
    grade VARCHAR(20) NOT NULL,
    total_score DECIMAL(10,2) DEFAULT 0.00 COMMENT '团体总分'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 比赛项目表
CREATE TABLE IF NOT EXISTS events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    parent_event_id INT NULL COMMENT '父级比赛ID，用于分组比赛',
    event_name VARCHAR(100) NOT NULL,
    group_number INT DEFAULT 1 COMMENT '组别号码',
    total_groups INT DEFAULT 1 COMMENT '总组数',
    participant_count INT DEFAULT 0 COMMENT '参赛人数',
    event_time DATETIME NOT NULL COMMENT '比赛时间',
    event_day INT DEFAULT 1 COMMENT '比赛日期（第几天）',
    status ENUM('未开始', '检录中', '比赛中', '公布成绩', '已结束') DEFAULT '未开始',
    is_record_breaking BOOLEAN DEFAULT FALSE COMMENT '是否有破纪录',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_event_id) REFERENCES events(event_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 运动会设置表
CREATE TABLE IF NOT EXISTS sports_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    sports_name VARCHAR(100) NOT NULL COMMENT '运动会名称',
    start_date DATE NOT NULL COMMENT '运动会开始日期',
    days INT DEFAULT 2 COMMENT '持续天数',
    current_day INT DEFAULT 1 COMMENT '当前第几天',
    is_active BOOLEAN DEFAULT TRUE COMMENT '是否激活',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初始化运动会设置
INSERT INTO sports_settings (sports_name, start_date, days, current_day) 
VALUES ('2024年校运动会', CURDATE(), 2, 1);

-- 参赛人员表
CREATE TABLE IF NOT EXISTS participants (
    participant_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    gender ENUM('男', '女') NOT NULL,
    class_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 比赛成绩表
CREATE TABLE IF NOT EXISTS results (
    result_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    participant_id INT NOT NULL,
    score VARCHAR(50) NOT NULL COMMENT '成绩，可能是时间或距离等',
    ranking INT COMMENT '名次',
    points INT DEFAULT 0 COMMENT '得分',
    is_record_breaking BOOLEAN DEFAULT FALSE COMMENT '是否破纪录',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES participants(participant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 比赛记录表（存储历史最好成绩）
CREATE TABLE IF NOT EXISTS records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(100) NOT NULL COMMENT '比赛项目名称（不含组别）',
    record_score VARCHAR(50) NOT NULL COMMENT '记录成绩',
    participant_name VARCHAR(50) COMMENT '记录保持者',
    class_name VARCHAR(50) COMMENT '班级',
    record_date DATE COMMENT '创建记录日期',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 管理员表
CREATE TABLE IF NOT EXISTS admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 比赛-参赛人员关联表
CREATE TABLE IF NOT EXISTS event_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    participant_id INT NOT NULL,
    lane_number INT COMMENT '道次',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (event_id, participant_id),
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES participants(participant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI生成的比赛动态和成绩报告表
CREATE TABLE IF NOT EXISTS ai_reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL COMMENT '关联的比赛ID',
    report_type ENUM('成绩报告', '比赛动态') NOT NULL COMMENT '报告类型',
    content TEXT NOT NULL COMMENT 'AI生成的内容',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初始化管理员账号
INSERT INTO admins (username, password) VALUES ('admin', MD5('123456')); 