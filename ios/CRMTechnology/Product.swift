import Foundation

struct Product: Identifiable, Codable, Hashable {
    let id: Int
    let slug: String
    let name: String
    let brand: String
    let image: String?
    let priceCents: Int?
    let stock: Int?
    let isDemo: Bool

    enum CodingKeys: String, CodingKey {
        case id, slug, name, brand, image, stock
        case priceCents = "price_cents", isDemo = "is_demo"
    }

    init(from decoder: Decoder) throws {
        let values = try decoder.container(keyedBy: CodingKeys.self)
        func integer(_ key: CodingKeys) -> Int? {
            if let value = try? values.decode(Int.self, forKey: key) { return value }
            if let value = try? values.decode(String.self, forKey: key) { return Int(value) }
            return nil
        }
        guard let identity = integer(.id) else {
            throw DecodingError.dataCorruptedError(forKey: .id, in: values, debugDescription: "Missing product identity")
        }
        id = identity
        slug = try values.decode(String.self, forKey: .slug)
        name = try values.decode(String.self, forKey: .name)
        brand = (try? values.decode(String.self, forKey: .brand)) ?? ""
        image = try? values.decode(String.self, forKey: .image)
        priceCents = integer(.priceCents)
        stock = integer(.stock)
        isDemo = (try? values.decode(Bool.self, forKey: .isDemo)) ?? (integer(.isDemo) == 1)
    }

    var formattedPrice: String {
        guard let cents = priceCents, cents >= 0 else { return "Price on enquiry" }
        let formatter = NumberFormatter()
        formatter.numberStyle = .currency
        formatter.currencyCode = "ZAR"
        formatter.locale = Locale(identifier: "en_ZA")
        return formatter.string(from: NSDecimalNumber(decimal: Decimal(cents) / Decimal(100))) ?? "Price on enquiry"
    }
}

struct CatalogPage: Decodable {
    let products: [Product]
    let total: Int
    let page: Int
    let pages: Int
}
