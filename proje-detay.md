# QR Menü SaaS Platformu – Proje Detayı

## Projenin Amacı

Restoranlar için üretime hazır, ölçeklenebilir, çok kiracılı (multi-tenant) bir QR Menü SaaS platformu geliştirmek.

**Temel Hedefler:**
- Restoran kayıt olabilir
- QR menüler oluşturulabilir
- QR menü sayfaları mini web sitesi gibi çalışır
- Sistem 1.000+ restoranı destekleyebilir
- Önce MVP, sonra ölçeklenebilirlik

**Kapsam Dışı (Kesinlikle Yok):**
- Sipariş alma
- Ödeme
- Garson çağırma
- Bildirim sistemi
- Analitik / raporlama

---

## Sistem Mimarisi (Kilitli – Değiştirilemez)

| Katman              | Teknoloji                          |
|---------------------|------------------------------------|
| Backend             | PHP 8.2+, Symfony (Monolit API)    |
| Veritabanı          | PostgreSQL                         |
| ORM                 | Doctrine                           |
| Public Frontend     | Next.js (sadece read-only)         |
| Admin Panel         | Symfony (Twig / Symfony UX)        |
| Kimlik Doğrulama    | JWT + Rol Bazlı Yetkilendirme      |
| Dosya Depolama      | S3 uyumlu                          |
| Deployment          | Docker + VPS                       |

**Roller:** SUPER_ADMIN, RESTAURANT_OWNER

---

## Geliştirme Fazları (Sıralı – Bir faz bitmeden sonrakine geçilmez)

### FAZ 1 – Temel Altyapı
- Symfony proje yapısı
- Auth & security
- Tenant izolasyonu
- Core entity'ler ve migration'lar

### FAZ 2 – Ana İş Mantığı
- Restoran onboarding
- Menü, kategori, ürün yönetimi
- Onay (approval) akışı
- Global katalog mantığı

### FAZ 3 – Public QR Menü
- Public read-only API
- Performans optimizasyonları
- Tema render altyapısı

### FAZ 4 – QR & PDF
- QR kod üretimi
- PDF şablonları ve çıktı

---

## Mevcut Durum

- **Aktif Faz:** FAZ 1 – Temel Altyapı (henüz başlanmadı)
- **Aktif Branch:** `feature/1-proje-ozeti`
- **Repo Durumu:** Sadece ROLE.md dosyası mevcut; henüz kod yazılmamış
- **Ana Branch:** `main`

---

## Git Stratejisi & İş Akışı

- `master`: Production (kutsal, dokunulmaz)
- `develop`: Staging (bir sonraki sürüm adayı)
- `feature/<issue-id>-<slug>`: Geliştirme dalları

**Akış:** feature dalı acilir → geliştirme yapılır → develop'a PR açılır → Admin (Gökhan) onaylar ve merge eder.

---

## Çalışma Kuralları

1. **MVP odaklı:** Kapsamda olmayan hiçbir özellik eklenmez
2. **Mimari kilitli:** Onaysız mimari değişiklik yapılamaz
3. **Overengineering yasak:** Gereksiz abstraction, pattern veya katman eklenmez
4. **Onay zorunlu:** DB şeması değişikliği, yeni servis/katman eklenmesi onay gerektirir
5. **Yasak pattern'ler:** Microservice, CQRS, Event Sourcing kullanılamaz
6. **Şüphede MVP kazanır:** Basit olan tercih edilir
