<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';

// Require login to access this page
requireLogin();

$canManageBooks = isAdmin() || staffHasPermission('books.manage');

// Load reusable category options for add/edit forms
$bookCategories = [];
try {
    $stmt = $pdo->query("
        SELECT DISTINCT category
        FROM books
        WHERE category IS NOT NULL AND TRIM(category) <> ''
        ORDER BY category ASC
    ");
    $bookCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $bookCategories = [];
}

// Process form submissions
$action = $_GET['action'] ?? '';
$bookId = $_GET['id'] ?? 0;

if (isStaff()) {
    if (in_array($action, ['delete', 'add', 'edit', 'print_barcode'], true) && !$canManageBooks) {
        setFlashMessage('You do not have permission to perform that action.', 'error');
        header('Location: books.php');
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$canManageBooks) {
        setFlashMessage('You do not have permission to modify books.', 'error');
        header('Location: books.php');
        exit;
    }
}

// Handle book deletion
if ($action === 'delete' && $bookId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM books WHERE id = :id");
        $stmt->bindParam(':id', $bookId, PDO::PARAM_INT);
        $stmt->execute();
        
        setFlashMessage('Book deleted successfully', 'success');
    } catch (PDOException $e) {
        setFlashMessage('Error deleting book: ' . $e->getMessage(), 'error');
    }
    
    header('Location: books.php');
    exit;
}

// Print book barcode
if ($action === 'print_barcode' && $bookId) {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE id = :id");
    $stmt->bindParam(':id', $bookId, PDO::PARAM_INT);
    $stmt->execute();
    $bookData = $stmt->fetch();
    
    if ($bookData) {
        // Display barcode print page
        include 'includes/header.php';
        ?>
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Book Barcode</h2>
                <p class="text-gray-600 dark:text-gray-400">Print barcode for "<?php echo htmlspecialchars($bookData['title']); ?>"</p>
            </div>
            <div>
                <a href="books.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                </a>
                <button onclick="printBarcode()" class="ml-2 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-print mr-2"></i> Print
                </button>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
            <div id="barcode-content" class="max-w-md mx-auto text-center p-8">
                <div class="mb-4">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white"><?php echo htmlspecialchars($bookData['title']); ?></h3>
                    <p class="text-gray-600 dark:text-gray-400">by <?php echo htmlspecialchars($bookData['author']); ?></p>
                    <?php if (!empty($bookData['isbn'])): ?>
                        <p class="text-gray-500 dark:text-gray-400">ISBN: <?php echo htmlspecialchars($bookData['isbn']); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="my-8">
                    <svg class="barcode-large mx-auto" jsbarcode-format="CODE128" jsbarcode-value="<?php echo htmlspecialchars($bookData['barcode']); ?>" jsbarcode-textmargin="0" jsbarcode-height="80" jsbarcode-fontoptions="bold" jsbarcode-fontSize="16" jsbarcode-width="2"></svg>
                </div>
                
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">EVSU Book Borrowing System</p>
            </div>
        </div>
        
        <style>
            @media print {
                header, .mb-6, footer {
                    display: none;
                }
                .barcode-large {
                    max-width: 100%;
                }
                body {
                    background: white;
                }
                .bg-white {
                    background: white !important;
                    box-shadow: none !important;
                    border: none !important;
                }
                .rounded-xl {
                    border-radius: 0 !important;
                }
            }
            .barcode-large {
                width: 300px;
                padding: 10px;
                background: white;
                border: 1px solid #e2e8f0;
                border-radius: 0.375rem;
            }
        </style>
        
        <script>
            function printBarcode() {
                window.print();
            }
            
            // Render barcode when page is loaded
            document.addEventListener('DOMContentLoaded', function() {
                JsBarcode(".barcode-large").init();
            });
        </script>
        
        <?php
        include 'includes/footer.php';
        exit;
    } else {
        setFlashMessage('Book not found', 'error');
        header('Location: books.php');
        exit;
    }
}

// Handle book form submission (add/edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $author = $_POST['author'] ?? '';
    $isbn = $_POST['isbn'] ?? '';
    $category = trim($_POST['category'] ?? '');
    $categorySelect = trim($_POST['category_select'] ?? '');
    $newCategory = trim($_POST['new_category'] ?? '');
    if ($newCategory !== '') {
        $category = $newCategory;
    } elseif ($categorySelect !== '') {
        $category = $categorySelect;
    }
    $description = $_POST['description'] ?? '';
    $barcode = $_POST['barcode'] ?? '';
    $stock = intval($_POST['stock'] ?? 1);
    
    // Set status based on stock
    $status = $stock > 0 ? 'Available' : 'Borrowed';
    
    // Validate required fields
    if (empty($title) || empty($author)) {
        setFlashMessage('Title and author are required', 'error');
    } else {
        try {
            // Handle cover image upload
            $cover_image_path = null;
            if (!empty($_FILES['cover_image']['name'])) {
                // Create uploads directory if it doesn't exist
                $target_dir = "uploads/books/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES["cover_image"]["name"], PATHINFO_EXTENSION);
                $new_filename = uniqid() . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;
                
                // Check if file is an actual image
                $check = getimagesize($_FILES["cover_image"]["tmp_name"]);
                if ($check !== false) {
                    if (move_uploaded_file($_FILES["cover_image"]["tmp_name"], $target_file)) {
                        $cover_image_path = $target_file;
                    }
                }
            }
            
            // If no barcode provided, generate one
            if (empty($barcode)) {
                $barcode = generateBookBarcode();
            }
            
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                // Update existing book
                $update_sql = "
                    UPDATE books 
                    SET title = :title, author = :author, isbn = :isbn, 
                        category = :category, description = :description, 
                        barcode = :barcode, status = :status, stock = :stock
                ";
                
                // Only update cover image if a new one was uploaded
                if ($cover_image_path) {
                    $update_sql .= ", cover_image_path = :cover_image_path";
                }
                
                $update_sql .= " WHERE id = :id";
                
                $stmt = $pdo->prepare($update_sql);
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':author', $author);
                $stmt->bindParam(':isbn', $isbn);
                $stmt->bindParam(':category', $category);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':barcode', $barcode);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':stock', $stock, PDO::PARAM_INT);
                $stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
                
                if ($cover_image_path) {
                    $stmt->bindParam(':cover_image_path', $cover_image_path);
                }
                
                $stmt->execute();
                
                setFlashMessage('Book updated successfully', 'success');
            } else {
                // Add new book
                $stmt = $pdo->prepare("
                    INSERT INTO books (title, author, isbn, category, description, barcode, status, stock, cover_image_path)
                    VALUES (:title, :author, :isbn, :category, :description, :barcode, :status, :stock, :cover_image_path)
                ");
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':author', $author);
                $stmt->bindParam(':isbn', $isbn);
                $stmt->bindParam(':category', $category);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':barcode', $barcode);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':stock', $stock, PDO::PARAM_INT);
                $stmt->bindParam(':cover_image_path', $cover_image_path);
                $stmt->execute();
                
                setFlashMessage('Book added successfully', 'success');
            }
            
            header('Location: books.php');
            exit;
        } catch (PDOException $e) {
            setFlashMessage('Error: ' . $e->getMessage(), 'error');
        }
    }
}

// Get book data for editing
$bookData = null;
if ($action === 'edit' && $bookId) {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE id = :id");
    $stmt->bindParam(':id', $bookId, PDO::PARAM_INT);
    $stmt->execute();
    $bookData = $stmt->fetch();
    
    if (!$bookData) {
        setFlashMessage('Book not found', 'error');
        header('Location: books.php');
        exit;
    }
}

// Get all books for listing
$search = $_GET['search'] ?? '';
$availability = $_GET['availability'] ?? (isStaff() && !$canManageBooks ? 'available' : 'all');

$canStaffBrowseByGenre = isStaff() && staffHasPermission('books.view');
$selectedGenre = '';
$bookSortKey = $_GET['book_sort'] ?? 'title_asc';
$genres = [];

// Whitelist sorting options
$sortOrderMap = [
    'title_asc' => 'title ASC, id DESC',
    'title_desc' => 'title DESC, id DESC',
    'newest' => 'created_at DESC, id DESC',
    'availability' => 'stock DESC, title ASC, id DESC',
];

if ($canStaffBrowseByGenre) {
    // Load available genres for dropdown
    $stmt = $pdo->query("
        SELECT DISTINCT category
        FROM books
        WHERE category IS NOT NULL AND category <> ''
        ORDER BY category ASC
    ");
    $genres = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Validate selected genre value
    $candidateGenre = $_GET['genre'] ?? '';
    if (!empty($candidateGenre) && in_array($candidateGenre, $genres, true)) {
        $selectedGenre = $candidateGenre;
    }

    // Validate sort key
    if (!isset($sortOrderMap[$bookSortKey])) {
        $bookSortKey = 'title_asc';
    }
} else {
    // Keep a predictable default order for non-staff browsing
    if (!isset($sortOrderMap[$bookSortKey])) {
        $bookSortKey = 'title_asc';
    }
}

$where = [];
$params = [];

if ($availability === 'available') {
    $where[] = 'stock > 0';
}

if ($canStaffBrowseByGenre && !empty($selectedGenre)) {
    $where[] = 'category = :category';
    $params[':category'] = $selectedGenre;
}

if (!empty($search)) {
    $where[] = '(title LIKE :search OR author LIKE :search OR isbn LIKE :search OR category LIKE :search OR barcode LIKE :search OR description LIKE :search)';
    $params[':search'] = "%$search%";
}

$sql = 'SELECT * FROM books';
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY ' . ($sortOrderMap[$bookSortKey] ?? $sortOrderMap['title_asc']);

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$books = $stmt->fetchAll();

// Include header
include 'includes/header.php';
?>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <!-- Add/Edit Book Form -->
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                <?php echo ($action === 'edit') ? 'Edit Book' : 'Add New Book'; ?>
            </h2>
            <p class="text-gray-600 dark:text-gray-400">
                <?php echo ($action === 'edit') ? 'Update book information' : 'Add a new book to the library'; ?>
            </p>
        </div>
        <a href="books.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Back to List
        </a>
    </div>
    
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
        <form method="POST" action="books.php" enctype="multipart/form-data">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?php echo $bookData['id']; ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title *</label>
                    <input type="text" id="title" name="title" required 
                           value="<?php echo ($bookData) ? htmlspecialchars($bookData['title']) : ''; ?>"
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
                
                <div>
                    <label for="author" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Author *</label>
                    <input type="text" id="author" name="author" required 
                           value="<?php echo ($bookData) ? htmlspecialchars($bookData['author']) : ''; ?>"
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
                
                <div>
                    <label for="isbn" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ISBN</label>
                    <input type="text" id="isbn" name="isbn" 
                           value="<?php echo ($bookData) ? htmlspecialchars($bookData['isbn']) : ''; ?>"
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
                
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
                    <?php
                        $selectedCategory = ($bookData && !empty($bookData['category'])) ? trim($bookData['category']) : '';
                        $availableCategoryOptions = $bookCategories;
                        if ($selectedCategory !== '' && !in_array($selectedCategory, $availableCategoryOptions, true)) {
                            $availableCategoryOptions[] = $selectedCategory;
                            sort($availableCategoryOptions, SORT_NATURAL | SORT_FLAG_CASE);
                        }
                    ?>
                    <input type="hidden" id="category" name="category" value="<?php echo htmlspecialchars($selectedCategory); ?>">
                    <div class="flex items-center gap-2">
                        <select id="category_select" name="category_select"
                                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select category</option>
                            <?php foreach ($availableCategoryOptions as $categoryOption): ?>
                                <option value="<?php echo htmlspecialchars($categoryOption); ?>" <?php echo ($selectedCategory === $categoryOption) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoryOption); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="toggle-category-editor"
                                class="shrink-0 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                            Edit Categories
                        </button>
                    </div>
                    <div id="category-editor" class="mt-2 hidden">
                        <label for="new_category" class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Add New Category</label>
                        <input type="text" id="new_category" name="new_category"
                               placeholder="Enter new category"
                               class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        <p class="text-xs text-gray-500 mt-1">If filled, this new category will be used and saved.</p>
                    </div>
                </div>
                
                <div>
                    <label for="barcode" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Barcode</label>
                    <input type="text" id="barcode" name="barcode" 
                           value="<?php echo ($bookData) ? htmlspecialchars($bookData['barcode']) : ''; ?>"
                           placeholder="Leave empty to auto-generate"
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
                
                <div>
                    <label for="stock" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stock</label>
                    <input type="number" id="stock" name="stock" min="0"
                           value="<?php echo ($bookData) ? htmlspecialchars($bookData['stock']) : '1'; ?>"
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    <p class="text-xs text-gray-500 mt-1">Enter 0 if the book is currently unavailable</p>
                </div>
                
                <div>
                    <label for="cover_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cover Image</label>
                    <input type="file" id="cover_image" name="cover_image" accept="image/*"
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    <?php if ($bookData && !empty($bookData['cover_image_path'])): ?>
                        <div class="mt-2">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Current cover: 
                                <a href="<?php echo htmlspecialchars($bookData['cover_image_path']); ?>" class="text-blue-600 dark:text-blue-400" target="_blank">View</a>
                            </p>
                        </div>
                    <?php endif; ?>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Upload a book cover (JPG, PNG)</p>
                </div>
                
                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                    <textarea id="description" name="description" rows="4"
                              class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"><?php echo ($bookData) ? htmlspecialchars($bookData['description']) : ''; ?></textarea>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end">
                <a href="books.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg mr-2">Cancel</a>
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg">
                    <?php echo ($action === 'edit') ? 'Update Book' : 'Add Book'; ?>
                </button>
            </div>
        </form>
    </div>
    <script>
        const categoryToggleBtn = document.getElementById('toggle-category-editor');
        const categoryEditor = document.getElementById('category-editor');
        const categorySelect = document.getElementById('category_select');
        const newCategoryInput = document.getElementById('new_category');

        if (categoryToggleBtn && categoryEditor) {
            categoryToggleBtn.addEventListener('click', function () {
                categoryEditor.classList.toggle('hidden');
                if (!categoryEditor.classList.contains('hidden') && newCategoryInput) {
                    newCategoryInput.focus();
                }
            });
        }

        if (newCategoryInput && categorySelect) {
            newCategoryInput.addEventListener('input', function () {
                if (this.value.trim() !== '') {
                    categorySelect.value = '';
                }
            });
        }
    </script>
<?php else: ?>
    <!-- Books List -->
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $canManageBooks ? 'Book Management' : 'Books'; ?></h2>
            <p class="text-gray-600 dark:text-gray-400"><?php echo $canManageBooks ? 'Manage library book collection' : 'View books in the library collection'; ?></p>
        </div>
        <?php if ($canManageBooks): ?>
        <a href="books.php?action=add" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg flex items-center">
            <i class="fas fa-plus mr-2"></i> Add Book
        </a>
        <?php endif; ?>
    </div>
    
    <!-- Search Bar -->
    <div class="mb-6">
        <form method="GET" action="books.php" class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4 flex flex-wrap items-center gap-4">
            <?php if (isStaff() && !$canManageBooks): ?>
            <div class="w-full sm:w-auto">
                <label for="availability" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Show</label>
                <select id="availability" name="availability"
                        class="w-full sm:w-48 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-white">
                    <option value="available" <?php echo $availability === 'available' ? 'selected' : ''; ?>>Available only</option>
                    <option value="all" <?php echo $availability === 'all' ? 'selected' : ''; ?>>All books</option>
                </select>
            </div>
            <?php endif; ?>

            <?php if ($canStaffBrowseByGenre): ?>
            <div class="w-full sm:w-auto">
                <label for="genre" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Genre</label>
                <select id="genre" name="genre"
                        onchange="this.form.submit()"
                        class="w-full sm:w-48 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-white">
                    <option value="" <?php echo empty($selectedGenre) ? 'selected' : ''; ?>>All Genres</option>
                    <?php foreach ($genres as $genre): ?>
                        <option value="<?php echo htmlspecialchars($genre); ?>" <?php echo ($genre === $selectedGenre) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($genre); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="w-full sm:w-auto">
                <label for="book_sort" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sort</label>
                <select id="book_sort" name="book_sort"
                        onchange="this.form.submit()"
                        class="w-full sm:w-48 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-white">
                    <option value="title_asc" <?php echo $bookSortKey === 'title_asc' ? 'selected' : ''; ?>>Title (A-Z)</option>
                    <option value="title_desc" <?php echo $bookSortKey === 'title_desc' ? 'selected' : ''; ?>>Title (Z-A)</option>
                    <option value="newest" <?php echo $bookSortKey === 'newest' ? 'selected' : ''; ?>>Newest</option>
                    <option value="availability" <?php echo $bookSortKey === 'availability' ? 'selected' : ''; ?>>Availability</option>
                </select>
            </div>
            <?php endif; ?>

            <div class="flex-1 min-w-[200px]">
                <input type="text" name="search" placeholder="Search by title, author, ISBN, category or barcode..." 
                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                    class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
            </div>
            <div>
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-search mr-2"></i> Search
                </button>
            </div>
            <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                <div>
                    <?php
                        $clearParams = [];
                        if (isStaff() && !$canManageBooks) {
                            $clearParams['availability'] = $availability;
                        }
                        if ($canStaffBrowseByGenre && !empty($selectedGenre)) {
                            $clearParams['genre'] = $selectedGenre;
                        }
                        if ($canStaffBrowseByGenre) {
                            $clearParams['book_sort'] = $bookSortKey;
                        }
                        $clearQueryString = !empty($clearParams) ? ('?' . http_build_query($clearParams)) : '';
                    ?>
                    <a href="books.php<?php echo htmlspecialchars($clearQueryString); ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center">
                        <i class="fas fa-times mr-2"></i> Clear
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>
    
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
                <?php if (!empty($search)): ?>
                    Search Results
                <?php elseif ($canStaffBrowseByGenre && !empty($selectedGenre)): ?>
                    Books in "<?php echo htmlspecialchars($selectedGenre); ?>"
                <?php elseif ($availability === 'available'): ?>
                    Available Books
                <?php else: ?>
                    All Books
                <?php endif; ?>
            </h3>
            <span class="text-gray-600 dark:text-gray-400">
                <?php if (!empty($search)): ?>
                    <?php echo count($books); ?> books found
                <?php else: ?>
                    <?php echo count($books); ?> books in library
                <?php endif; ?>
            </span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full responsive-table">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Book</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Author</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ISBN</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Barcode</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Stock</th>
                        <?php if ($canManageBooks): ?>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (count($books) > 0): ?>
                        <?php foreach ($books as $book): ?>
                            <tr>
                                <td class="px-6 py-4" data-label="Book">
                                    <div class="flex items-center">
                                        <?php if (!empty($book['cover_image_path'])): ?>
                                            <img class="w-16 h-16 rounded-xl object-cover border-2 border-gray-200 dark:border-gray-700" 
                                                 src="<?php echo htmlspecialchars($book['cover_image_path']); ?>" 
                                                 alt="<?php echo htmlspecialchars($book['title']); ?>">
                                        <?php else: ?>
                                            <div class="bg-gray-200 dark:bg-gray-700 border-2 border-dashed rounded-xl w-16 h-16 flex items-center justify-center">
                                                <i class="fas fa-book text-gray-400 dark:text-gray-500 text-2xl"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($book['title']); ?></div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($book['category']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white" data-label="Author"><?php echo htmlspecialchars($book['author']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400" data-label="ISBN"><?php echo htmlspecialchars($book['isbn']); ?></td>
                                <td class="px-6 py-4" data-label="Barcode">
                                    <svg class="barcode-canvas" jsbarcode-format="CODE128" jsbarcode-value="<?php echo htmlspecialchars($book['barcode']); ?>" jsbarcode-textmargin="0" jsbarcode-fontoptions="bold" jsbarcode-height="40"></svg>
                                </td>
                                <td class="px-6 py-4" data-label="Stock">
                                    <?php if ($book['stock'] > 0): ?>
                                        <span class="px-3 py-1 text-sm rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            <?php echo htmlspecialchars($book['stock']); ?> in stock
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 text-sm rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                            Out of stock
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canManageBooks): ?>
                                <td class="px-6 py-4 text-right text-sm" data-label="Actions">
                                    <div class="flex justify-end space-x-3">
                                        <a href="books.php?action=print_barcode&id=<?php echo $book['id']; ?>" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <a href="books.php?action=edit&id=<?php echo $book['id']; ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars(addslashes($book['title'])); ?>')" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $canManageBooks ? 6 : 5; ?>" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                <?php echo $canManageBooks ? 'No books found. Add a book to get started.' : 'No books found for this filter.'; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-md w-full">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Confirm Deletion</h3>
            <p class="text-gray-700 dark:text-gray-300 mb-6">Are you sure you want to delete "<span id="deleteBookTitle"></span>"? This action cannot be undone.</p>
            <div class="flex justify-end">
                <button id="cancelDelete" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg mr-2">Cancel</button>
                <a id="confirmDeleteBtn" href="#" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg">Delete</a>
            </div>
        </div>
    </div>
    
    <script>
        function confirmDelete(id, title) {
            document.getElementById('deleteBookTitle').textContent = title;
            document.getElementById('confirmDeleteBtn').href = 'books.php?action=delete&id=' + id;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        document.getElementById('cancelDelete').addEventListener('click', function() {
            document.getElementById('deleteModal').classList.add('hidden');
        });

    </script>
<?php endif; ?>

<?php
// Include footer
include 'includes/footer.php';
?> 