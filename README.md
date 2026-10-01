# Team Competition Management

เว็บสำหรับจัดการทีมและงานแข่ง (competition events) — สร้างทีม เชิญสมาชิก สร้างงาน กำหนดวัน/แท็ก ลงทะเบียน และดูปฏิทิน ตามสิทธิ์ของแต่ละคน

## ทำอะไรได้บ้าง

- **ทีม** — สร้างทีม เชิญสมาชิก จัด role (owner / admin / member) โอนเจ้าของ
- **งานแข่ง** — สร้าง/แก้ไข/ลบ event ผูกกับทีม กำหนด visibility วันที่ และแท็ก
- **ลงทะเบียน** — สมัครเข้าร่วมงานแบบบุคคลหรือทีม ดูปฏิทินงาน
- **บัญชี** — สมัคร/เข้าสู่ระบบ ยืนยันอีเมล รีเซ็ตรหัสผ่าน ตั้งค่าโปรไฟล์
- **แอดมินระบบ** — ดู activity log ทั้งระบบ

## Tech stack

PHP 8.2 · Tailwind CSS + DaisyUI · MariaDB · Docker Compose

## เริ่มใช้งานเร็วๆ

```bash
cp .env.example .env
docker compose up -d --build
```

- Web: http://localhost:8000  
- phpMyAdmin: http://localhost:8080  

รายละเอียดติดตั้ง โครงสร้างโปรเจกต์ routing schema และบทบาทผู้ใช้ → ดู **[GUIDE.md](GUIDE.md)**

## License

MIT
