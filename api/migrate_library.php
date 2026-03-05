<?php
/**
 * 数据库迁移脚本：从 library.html 迁移书籍数据到数据库
 */

// 1. 统一由 session.php 处理 Session 设置，解决登录失效问题
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
// 强制包含数据库配置文件
require_once dirname(__DIR__) . '/includes/db_config.php';
// 屏蔽错误报告，防止警告干扰 JSON 输出
error_reporting(0);

// 权限验证
if (!isset($_SESSION['user_id'])) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('HTTP/1.1 401 Unauthorized');
    }
    echo json_encode(array(
        'success' => false,
        'code' => 401,
        'msg' => 'Unauthorized: 请先登录'
    ));
    exit;
}

// 设置响应头为 JSON
header('Content-Type: application/json; charset=utf-8');

function log_message($msg) {
    echo $msg . "\n";
}

try {
    $conn = db_connect();
    if (!$conn) {
        throw new Exception("数据库连接失败");
    }

    // 1. 创建表结构
    $create_table_sql = "CREATE TABLE IF NOT EXISTS `site_library` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `author` VARCHAR(255),
        `publisher` VARCHAR(255),
        `isbn` VARCHAR(20),
        `price` DECIMAL(10, 2),
        `rating` DECIMAL(3, 1),
        `category` VARCHAR(50),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    if (!$conn->query($create_table_sql)) {
        throw new Exception("创建表失败: " . $conn->error);
    }
    log_message("表 `site_library` 已就绪");

    // 2. 准备书籍数据和分类逻辑
    // 这里直接硬编码从 library.html 提取的数据，避免复杂的 HTML 解析
    $categoryMap = array(
        '文学小说' => array('平凡的世界', '百年孤独', '活着', '1984', '三体', '小王子', '白夜行', '追风筝的人', '解忧杂货店', '围城', '红楼梦', '西游记', '水浒传', '三国演义', '金庸', '鲁迅', '村上春树', '东野圭吾', '马尔克斯', '余华', '莫言'),
        '历史哲学' => array('人类简史', '大国崛起', '万历十五年', '明朝那些事儿', '史记', '资治通鉴', '苏菲的世界', '存在与虚无', '论语', '道德经', '庄子', '历史', '哲学', '文化', '宗教', '考古'),
        '社会科学' => array('乌合之众', '社会心理学', '经济学原理', '国富论', '自私的基因', '枪炮、病菌与钢铁', '心理学', '经济', '社会', '政治', '法律', '教育', '传播'),
        '科技科普' => array('时间简史', '宇宙', '自私的基因', '上帝掷骰子吗', '算法', '代码', '程序', '物理', '化学', '生物', '数学', '计算机', '人工智能', '互联网'),
        '艺术设计' => array('艺术的故事', '设计心理学', '色彩构成', '平面设计', '摄影', '绘画', '音乐', '电影', '建筑', '美学'),
        '生活百科' => array('饮食', '健康', '旅游', '摄影', '家居', '育儿', '美食', '职场', '理财', '工具书')
    );

    function getCategory($title, $categoryMap) {
        foreach ($categoryMap as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($title, $keyword) !== false) {
                    return $category;
                }
            }
        }
        return '其他';
    }

    // 数据片段（由于数据量大，这里只放一部分作为示例，实际操作时应完整提取或分批处理）
    // 为了保证迁移完整性，我会从 library.html 中提取完整的 JSON 字符串
    $html_content = file_get_contents(dirname(__DIR__) . '/pages/library.html');
    preg_match('/const\s+books\s*=\s*(\[.*?\]);/s', $html_content, $matches);
    
    if (!isset($matches[1])) {
        throw new Exception("无法在 library.html 中找到书籍数据");
    }

    $books = json_decode($matches[1], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON 解析失败: " . json_last_error_msg());
    }

    log_message("共找到 " . count($books) . " 本书籍，准备导入...");

    // 3. 开始迁移
    $conn->begin_transaction();
    
    // 先清空旧数据（可选，根据需求决定）
    if (!$conn->query("TRUNCATE TABLE `site_library` ")) {
        echo "Truncate site_library failed: " . $conn->error . "\n";
    }

    $stmt = $conn->prepare("INSERT INTO `site_library` (title, author, publisher, isbn, price, rating, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare insert failed: " . $conn->error);
    }
    
    $count = 0;
    foreach ($books as $book) {
        $title = $book['title'];
        $author = isset($book['author']) ? $book['author'] : '';
        $publisher = isset($book['publisher']) ? $book['publisher'] : '';
        $isbn = isset($book['isbn']) ? $book['isbn'] : '';
        $price = isset($book['price']) ? (float)$book['price'] : 0.0;
        $rating = isset($book['rating']) ? (float)$book['rating'] : 0.0;
        $category = getCategory($title, $categoryMap);

        $stmt->bind_param("ssssdds", $title, $author, $publisher, $isbn, $price, $rating, $category);
        
        if (!$stmt->execute()) {
            $conn->rollback();
            throw new Exception("导入书籍失败: " . $title . " - " . $stmt->error);
        }
        $count++;
    }

    $conn->commit();
    log_message("迁移成功！共导入 $count 条数据。");

} catch (Exception $e) {
    log_message("错误: " . $e->getMessage());
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
