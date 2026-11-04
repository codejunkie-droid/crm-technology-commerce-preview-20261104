import Foundation

struct APIClient {
    let baseURL: URL
    let session: URLSession

    static var configured: APIClient {
        let configured = Bundle.main.object(forInfoDictionaryKey: "CommerceBaseURL") as? String
        let url = URL(string: configured ?? "https://commerce.example.invalid")!
        return APIClient(baseURL: url, session: .shared)
    }

    func products(query: String, page: Int = 1) async throws -> CatalogPage {
        try validateConfiguration()
        var parts = URLComponents(url: baseURL.appendingPathComponent("api/products"), resolvingAgainstBaseURL: false)!
        parts.queryItems = [URLQueryItem(name: "q", value: query), URLQueryItem(name: "page", value: String(page))]
        var request = URLRequest(url: parts.url!)
        request.timeoutInterval = 20
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        let (data, response) = try await session.data(for: request)
        guard let http = response as? HTTPURLResponse, (200...299).contains(http.statusCode) else {
            throw APIError.unavailable
        }
        return try JSONDecoder().decode(CatalogPage.self, from: data)
    }

    func product(slug: String) async throws -> Product {
        try validateConfiguration()
        guard !slug.isEmpty, slug.range(of: "^[a-zA-Z0-9-]+$", options: .regularExpression) != nil else { throw APIError.invalidLink }
        let (data, response) = try await session.data(from: baseURL.appendingPathComponent("api/products").appendingPathComponent(slug))
        guard let http = response as? HTTPURLResponse, http.statusCode == 200 else { throw APIError.unavailable }
        return try JSONDecoder().decode(Product.self, from: data)
    }

    func productURL(_ product: Product) -> URL { baseURL.appendingPathComponent("products").appendingPathComponent(product.slug) }
    func imageURL(_ product: Product) -> URL? {
        guard let image = product.image, let url = URL(string: image, relativeTo: baseURL)?.absoluteURL,
              url.scheme == "https", url.host == baseURL.host else { return nil }
        return url
    }
    private func validateConfiguration() throws {
        guard baseURL.scheme == "https", let host = baseURL.host, !host.hasSuffix(".invalid") else { throw APIError.configuration }
    }
}

enum APIError: LocalizedError {
    case configuration, unavailable, invalidLink
    var errorDescription: String? {
        switch self {
        case .configuration: "Set CommerceBaseURL to your verified HTTPS staging deployment in the Xcode target settings."
        case .unavailable: "The catalog could not be loaded. Please try again."
        case .invalidLink: "This product link is not valid."
        }
    }
}
