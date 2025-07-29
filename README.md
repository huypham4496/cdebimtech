# cdebimtech
Mã nguồn CDE áp dụng BIM cho ngành GTVT
Cấu trúc:
cde-transport-project/
│
├── assets/
│   ├── css/
│   │   └── auth.css         # CSS cho form đăng nhập/đăng ký
│   └── images/
│       └── default-avatar.png  # ảnh avatar mặc định
│
├── includes/
│   ├── header.php
│   ├── footer.php
│   └── functions.php      # bổ sung hàm xử lý đăng ký, đăng nhập
│
├── pages/
│   ├── register.php       # Form và logic xử lý đăng ký
│   └── login.php          # Form và logic xử lý đăng nhập
│
├── sql/
│   └── create_tables.sql  # Tạo users table
│
├── config.php            # Kết nối PDO tới MySQL
└── README.md             # Hướng dẫn nhanh