import Foundation
import SwiftUI

@MainActor
final class StoreModel: ObservableObject {
    @Published var products: [Product] = []
    @Published var saved: [Product] = []
    @Published var query = ""
    @Published var loading = false
    @Published var error: String?
    @Published var path: [Product] = []
    @Published var hasMore = false
    private var page = 1
    private var requestID = UUID()
    private let savedKey = "crm.savedProducts.v1"
    let api = APIClient.configured

    init() {
        if let data = UserDefaults.standard.data(forKey: savedKey), let products = try? JSONDecoder().decode([Product].self, from: data) {
            saved = products
        }
    }
    func search(nextPage: Bool = false) async {
        let currentID = UUID()
        requestID = currentID
        loading = true
        error = nil
        let requestedPage = nextPage ? page + 1 : 1
        do {
            let response = try await api.products(query: query, page: requestedPage)
            guard requestID == currentID else { return }
            products = nextPage ? products + response.products : response.products
            page = response.page
            hasMore = response.page < response.pages
        } catch {
            guard requestID == currentID else { return }
            self.error = error.localizedDescription
        }
        if requestID == currentID { loading = false }
    }
    func toggleSaved(_ product: Product) {
        if saved.contains(where: { $0.id == product.id }) { saved.removeAll { $0.id == product.id } }
        else { saved.append(product) }
        if let data = try? JSONEncoder().encode(saved) { UserDefaults.standard.set(data, forKey: savedKey) }
    }
    func open(_ url: URL) async {
        // Custom-scheme links are navigation hints only; no account or payment action is accepted.
        guard url.scheme == "crmtechnology", url.host == "product" else { return }
        let slug = url.pathComponents.filter { $0 != "/" }.first ?? ""
        do { path.append(try await api.product(slug: slug)) }
        catch { self.error = error.localizedDescription }
    }
}
