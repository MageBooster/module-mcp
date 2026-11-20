# MageBooster AI connector (Mcp) kullanım kılavuzu

- **Paket:** `magebooster/module-mcp`
- **Lisans:** ücretli
- **Destek:** info@magebooster.com

## Ne işe yarar

Mcp modülü, Claude veya ChatGPT'nin **mcp.magebooster.com** ağ geçidi üzerinden mağazanızda çalışmasını sağlar. Ağ geçidi istekleri mağazanıza iletir, mağaza verilerinin kopyasını tutmaz.

Bağlantı **siz açana kadar kapalıdır**. Kapalıyken bağlantı adresi, var olmayan herhangi bir adres gibi "Not found" (bulunamadı) yanıtı verir.

### Bu sürümdeki araçlar

Bu sürümdeki tüm araçlar yalnızca veri okur. Mağazada hiçbir şeyi değiştirmez.

| Araç | Ne döndürür |
|---|---|
| `store_info` | Mağazanın temel bilgileri |
| `products_search` | Aramaya uyan ürünler |
| `product_get` | Tek bir ürünün ayrıntıları |
| `categories_tree` | Kategori ağacı |
| `orders_list` | En fazla 366 günlük bir dönemin siparişleri. Kişisel veriler maskelenir. |
| `sales_summary` | Bir dönemin satış toplamları |
| `abandoned_carts` | Son 7 ya da 30 günde terk edilen sepetler. Kişisel veriler maskelenir. |
| `maintenance_status` | Önbellek ve indeksleyicilerin durumu |

Reports modülü kuruluysa bu listeye rapor, soru ve uyarı araçlarını ekler. Ayrıntılar Reports kılavuzunda.

### Planlanan araçlar

Ürün, fiyat, stok ya da ayar değiştiren araçlar bu sürümde yoktur. `products_update` kaydı yalnızca bir yer tutucudur: araç listesinde görünmez ve çağrılamaz.

### Erişim düzeyleri

Bir bağlantının alabileceği en yüksek erişim düzeyini siz belirlersiniz:

- **Read only (yalnızca okuma):** sorular ve raporlar. Mağazada hiçbir şey değişmez.
- **Editor (düzenleyici):** ek olarak ürünler, fiyatlar, stok ve kategoriler. Bu sürümde bu düzeyi kullanan tek araçlar Reports modülünün uyarı araçlarıdır.
- **Full (tam):** ek olarak tema, ödeme sayfası ve mağaza ayarları. Bu sürümde bu düzeyi gerektiren bir araç yoktur.

Bir bağlantı daha fazlasını istese bile belirlediğiniz en yüksek düzeyi aşamaz.

### Güvenlik

- **İmzalı istekler:** Ağ geçidinden gelen her istek, bağlantı anahtarınızla HMAC-SHA256 kullanılarak imzalanır.
  - İstekte bir zaman damgası bulunur. Mağaza saatiyle arasındaki fark 5 dakikayı geçerse istek kabul edilmez.
  - Her istekte tek kullanımlık bir nonce değeri bulunur ve 10 dakika saklanır.
  - İmzası hatalı, zamanı geçmiş ya da nonce değeri tekrar kullanılmış istekler reddedilir.
- **Kayıt (audit log):** Her çağrı `mbmcp_audit` tablosuna yazılır. Kaydedilen bilgiler:
  - istemci, araç ve yetki düzeyi
  - kişisel veriler çıkarılmış olarak argümanlar
  - sonuç (ok, denied ya da error)
  - öğe sayısı ve süre
- **Mağaza onayı:** Yeni bir bağlantıyı Magento yönetim panelinde bir yönetici kullanıcının onaylaması gerekir.
- **Erişimi kesme:** Bağlantı anahtarını değiştirdiğinizde tüm bağlantılar bir anda kesilir.

## Gereksinimler

- PHP 8.0 veya üzeri
- Magento 2.4.4 – 2.4.9
- Bağlantı anahtarını veren bir mcp.magebooster.com hesabı
- **Luma sürümü:** `magebooster/module-mcp`
- **Hyvä sürümü:** `magebooster/module-mcp-hyva`

İki sürüm ayrı satılır. Modül sunucuda ve yönetim panelinde çalışır, mağaza vitrinine bir şey eklemez.

## Composer ile kurulum (ücretli paket)

1. Depoyu ekleyin:

   ```bash
   composer config repositories.magebooster composer https://repo.magebooster.com
   ```

2. Magento kök klasöründeki `auth.json` dosyasına erişim bilgilerinizi yazın. Kullanıcı adı sipariş e-postanızda yazar. Şifre olarak seri numaranızı kullanın.

   ```json
   {
     "http-basic": {
       "repo.magebooster.com": {
         "username": "<sipariş e-postasındaki kullanıcı adı>",
         "password": "<seri numaranız>"
       }
     }
   }
   ```

3. Paketi kurun:

   ```bash
   composer require magebooster/module-mcp
   ```

   Hyvä temalı mağazada bunun yerine `magebooster/module-mcp-hyva` paketini kurun.

`auth.json` dosyasını sürüm kontrolüne (git) eklemeyin.

**Seri numarası:** Bu sürümde seri numarası yalnızca paketi indirmek için kullanılır. Modül mağaza içinde seri numarası kontrolü yapmaz. Lisanslar sayfasında da bunun için bir alan yoktur.

## Zip dosyasıyla kurulum

1. Arşivi `app/code/MageBooster/Mcp` klasörüne açın.
2. Aşağıdaki komutları çalıştırın.

## Modülü etkinleştirme

```bash
bin/magento module:enable MageBooster_Mcp
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

## Yönetim paneli ayarları

Ayarlar **Stores > Configuration > MageBooster > AI connector (Claude, ChatGPT)** sayfasındadır.

| Alan | Varsayılan | Açıklama |
|---|---|---|
| Enabled | Hayır | Bağlantıyı açar. Kapalıyken bağlantı adresi "Not found" yanıtı verir. |
| Highest access | Read only | Bir bağlantının yapabileceklerinin üst sınırı. Seçenekler: Read only, Editor, Full. |
| Connection key | (boş) | mcp.magebooster.com'dan aldığınız anahtar. Şifrelenmiş olarak saklanır. Değiştirirseniz tüm bağlantılar kesilir. |

### Yetkiler (ACL)

- `MageBooster_Mcp::config`: yönetici kullanıcının bu ayarları değiştirmesine izin verir.
- `MageBooster_Mcp::connect`: yönetici kullanıcının yeni bağlantıları onaylamasına izin verir.

## Claude veya ChatGPT'yi bağlama

1. mcp.magebooster.com'dan bağlantı anahtarınızı alın.
2. Anahtarı **Connection key** alanına yapıştırın, **Enabled** ayarını Evet yapın, **Highest access** düzeyini seçin ve **Save Config** düğmesine tıklayın.
3. Claude veya ChatGPT'de MageBooster bağlantısını ekleyip girişi başlatın.
4. Giriş sizi önce mağazanıza (`/mbmcp/connect/approve`), ardından **"Claude or ChatGPT wants to work in <mağaza>"** başlıklı yönetim paneli sayfasına götürür. Önce yönetim paneline giriş yapmanız gerekebilir.
5. Bu bağlantının erişim düzeyini seçin. Seçtiğiniz düzey **Highest access** ayarını aşamaz.
6. Onaylamak için **Approve**, reddetmek için **Cancel** düğmesine tıklayın.
7. Claude veya ChatGPT'ye geri dönersiniz ve bağlantı kullanıma hazırdır.

Onay bağlantısı 10 dakika geçerlidir. Süre dolarsa Claude veya ChatGPT'den yeniden başlayın.

## Geliştiriciler için

### Uç noktalar

- `POST /mbmcp/agent/call` (mağaza tarafı rotası `mbmcp`), ağ geçidinden gelen çağrıları karşılar.
  - Gövde JSON'dur ve şu alanları içerir: `method` (`tools/list` ya da `tools/call`), `tool`, `args`, `role`, `client`, `request_id`.
  - Başlıklar: `X-MB-Ts`, `X-MB-Nonce`, `X-MB-Sig`.
  - İmza: `hex(hmac_sha256(anahtar, ts + "\n" + nonce + "\n" + hamGövde))`.
- Giriş akışını `GET /mbmcp/connect/approve` ve yönetim paneli rotası `mbmcp/connect/approve` yürütür.

### Hata kodları

Hatalar `{ok:false, error:{code, message}}` biçiminde JSON olarak döner. Kodlar:

- `not_configured`
- `bad_request`
- `too_large`
- `unauthorized`
- `stale_request`
- `nonce_reused`
- `bad_signature`
- `unknown_tool`
- `role_denied`
- `internal`

### Araç ekleme

Kendi araçlarınızı `di.xml` üzerinden modülün araç havuzuna (tool pool) kaydedersiniz. Her araç ihtiyaç duyduğu en düşük yetki düzeyini (read, editor ya da full) belirtir. Reports modülü çalışan bir örnektir.

## Test

1. **Enabled** kapalıyken `https://<mağazanız>/mbmcp/agent/call` adresini açın. "Not found" yanıtı almalısınız.
2. Bağlantıyı açın ve yukarıda anlatıldığı gibi Claude veya ChatGPT'den bağlanın.
3. "Geçen hafta kaç sipariş aldık?" gibi bir soru sorun.
4. `mbmcp_audit` tablosunda bu çağrının kaydını kontrol edin.
5. Bağlantı anahtarını değiştirip kaydedin. Claude veya ChatGPT'den gelen bir sonraki istek reddedilmelidir.

## Sorun giderme

- **"This connection link is not valid. Start again from Claude or ChatGPT."** Bağlantı adresi değiştirilmiş ya da mağazadaki anahtar mcp.magebooster.com'daki anahtarla aynı değil.
- **`not_configured`:** Bağlantı açık ama bağlantı anahtarı boş.
- **`stale_request`:** Sunucu saati yanlış. Saati NTP ile eşitleyin.
- **`role_denied`:** Araç, bu bağlantının sahip olduğundan yüksek bir düzey istiyor. İzin vermek istiyorsanız **Highest access** düzeyini yükseltip yeniden bağlanın.
- **İstekler Magento'ya ulaşmadan engelleniyor:** Güvenlik duvarınızın veya WAF'ınızın `POST /mbmcp/agent/call` isteklerine JSON gövdeyle ve `X-MB-*` başlıklarıyla izin verdiğinden emin olun.

## Kaldırma

Reports modülü bu modüle bağımlıdır. Kuruluysa önce Reports'u kaldırın.

1. **Enabled** ayarını Hayır yapın.
2. Claude veya ChatGPT'deki bağlantıyı kaldırın.
3. Şu komutları çalıştırın:

   ```bash
   bin/magento module:disable MageBooster_Mcp
   composer remove magebooster/module-mcp
   bin/magento setup:upgrade
   bin/magento cache:flush
   ```

`mbmcp_audit` tablosu veritabanında kalır. Kayıtlara ihtiyacınız yoksa silebilirsiniz.

## Lisans ve kopyalama

Ticari lisans, bkz. `LICENSE.txt`. Telif hakkı (c) 2026 WebWorks 101 OÜ (MageBooster). Tüm hakları saklıdır.

- Bir seri numarası = bir canlı kurulum (bir mağaza alan adı). Aynı mağazanın test (staging) ve geliştirme kopyaları serbesttir.
- Her seri numarası ve her indirme, satın alan müşteriye bağlıdır. Luma ve Hyvä sürümleri ayrı ürünlerdir, seri numaraları da ayrıdır.
- Kodu kendi lisanslı mağazanız için değiştirebilirsiniz. Telif ve lisans notlarını silmeyin.
- Yasak olanlar: başka mağazalara kopyalamak, dağıtmak, yeniden satmak, alt lisans vermek, herkese açık depolarda, pazar yerlerinde veya forumlarda yayınlamak, lisans kontrolünü ya da bu notları kaldırmak, seri numarasını paylaşmak.
- İzinsiz kopyalama veya kullanımda seri numarası iade yapılmadan iptal edilir, güncelleme ve destek durur; WebWorks 101 OÜ tazminat isteyebilir ve yasal yola gidebilir. Estonya hukuku geçerlidir; yetkili mahkeme Harju İlçe Mahkemesi'dir (Harju Maakohus).

## Destek

info@magebooster.com
